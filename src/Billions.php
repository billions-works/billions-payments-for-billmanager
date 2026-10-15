<?php

declare(strict_types=1);

/** BILLmanager bridge for the Billions Public API. Requires PHP 7.4+. */

final class BillionsError extends RuntimeException
{
}

final class BillionsApi
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if (!self::isHttpsOrigin($baseUrl)) {
            throw new BillionsError('API URL must be an HTTPS origin');
        }
        if (trim($apiKey) === '' || preg_match('/[\x00-\x20\x7f]/', $apiKey)) {
            throw new BillionsError('API key is required');
        }
        $this->baseUrl = $baseUrl;
        $this->apiKey = trim($apiKey);
    }

    public static function isHttpsOrigin(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['path']) && !isset($parts['query']) && !isset($parts['fragment']);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function createInvoice(array $payload): array
    {
        return $this->request('POST', '/public/v1/invoices/create', $payload);
    }

    /** @return array<string, mixed> */
    public function getInvoice(string $id): array
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i', $id)) {
            throw new BillionsError('Invalid invoice ID');
        }
        return $this->request('GET', '/public/v1/invoices/' . rawurlencode($id));
    }

    /** @param array<string, mixed>|null $payload @return array<string, mixed> */
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $headers = "Accept: application/json\r\nContent-Type: application/json\r\nx-api-key: {$this->apiKey}\r\n";
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $body,
            'timeout' => 20,
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);
        $response = @file_get_contents($this->baseUrl . $path, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $match)) {
                $status = (int) $match[1];
            }
        }
        if ($response === false || $status < 200 || $status >= 300) {
            throw new BillionsError('Billions API request failed (HTTP ' . $status . ')');
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new BillionsError('Invalid Billions API response');
        }
        return $decoded;
    }
}

final class BillionsBridge
{
    public const MODULE = 'pmbillions';

    /** @return array<string, string> */
    public static function payment(SimpleXMLElement $document): array
    {
        if (!isset($document->payment[0])) {
            throw new BillionsError('BILLmanager payment not found');
        }
        $node = $document->payment[0];
        $method = null;
        $currency = null;
        foreach ($node->paymethod as $candidate) {
            if (isset($candidate->module)) {
                $method = $candidate;
            }
        }
        foreach ($node->currency as $candidate) {
            if (isset($candidate->iso)) {
                $currency = $candidate;
            }
        }
        if ($method === null || $currency === null || (string) $method->module !== self::MODULE) {
            throw new BillionsError('Payment does not belong to Billions module');
        }
        $values = [
            'id' => (string) $node->id,
            'paymethod_id' => (string) $method->id,
            'amount' => (string) $node->paymethodamount,
            'currency' => strtoupper((string) $currency->iso),
            'description' => (string) $node->description,
            'manager_url' => (string) $node->manager_url,
            'externalid' => (string) $node->externalid,
            'api_key' => (string) $method->api_key,
            'api_url' => (string) $method->api_url,
            'instance_id' => (string) $method->instance_id,
            'callback_base_url' => (string) $method->callback_base_url,
        ];
        if (!ctype_digit($values['id']) || !ctype_digit($values['paymethod_id'])) {
            throw new BillionsError('Invalid BILLmanager payment ID');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{3,30}$/', $values['instance_id'])) {
            throw new BillionsError('Invalid BILLmanager instance ID');
        }
        if (!preg_match('/^\d+(?:\.\d+)?$/', $values['amount']) || self::decimal($values['amount']) === '0') {
            throw new BillionsError('Invalid payment amount');
        }
        if (!preg_match('/^[A-Z0-9_]{2,20}$/', $values['currency'])) {
            throw new BillionsError('Invalid payment currency');
        }
        if ($values['api_key'] === '') {
            throw new BillionsError('Missing API key');
        }
        return $values;
    }

    /** @param array<string, string> $payment */
    public static function orderNo(array $payment): string
    {
        return 'bm-' . $payment['instance_id'] . '-' . $payment['paymethod_id'] . '-' . $payment['id'];
    }

    /** @param array<string, string> $payment */
    public static function callbackUrl(array $payment): string
    {
        $base = rtrim($payment['callback_base_url'], '/');
        if (!BillionsApi::isHttpsOrigin($base)) {
            throw new BillionsError('Callback URL must be an HTTPS origin');
        }
        return $base . '/mancgi/billionsresult.php?elid=' . rawurlencode($payment['id']);
    }

    /** @param array<string, string> $payment @return array<string, mixed> */
    public static function createPayload(array $payment): array
    {
        $orderNo = self::orderNo($payment);
        $payload = [
            'currency' => $payment['currency'],
            'amount' => $payment['amount'],
            'type' => 'b2c',
            'order_no' => $orderNo,
            'idempotencyKey' => $orderNo,
            'callbackUrl' => self::callbackUrl($payment),
        ];
        if ($payment['description'] !== '') {
            $payload['description'] = mb_substr($payment['description'], 0, 255, 'UTF-8');
        }
        return $payload;
    }

    /** @param array<string, mixed> $invoice @param array<string, string> $payment */
    public static function assertInvoiceMatches(array $invoice, array $payment): void
    {
        if (($invoice['order_no'] ?? null) !== self::orderNo($payment)
            || strtoupper((string) ($invoice['settlement_currency'] ?? '')) !== $payment['currency']
            || self::decimal((string) ($invoice['amount'] ?? '')) !== self::decimal($payment['amount'])) {
            throw new BillionsError('Invoice does not match BILLmanager payment');
        }
        $externalId = $payment['externalid'];
        if ($externalId !== '' && $externalId !== (string) ($invoice['id'] ?? '')) {
            throw new BillionsError('Invoice ID does not match existing payment');
        }
    }

    public static function decimal(string $value): string
    {
        if (!preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new BillionsError('Invalid decimal amount');
        }
        $parts = explode('.', $value, 2);
        $whole = ltrim($parts[0], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = rtrim($parts[1] ?? '', '0');
        return $fraction === '' ? $whole : $whole . '.' . $fraction;
    }

    /** @param array<string, mixed> $webhook */
    public static function webhookInvoiceId(array $webhook): string
    {
        $data = isset($webhook['data']) && is_array($webhook['data']) ? $webhook['data'] : $webhook;
        return trim((string) ($data['invoice_id'] ?? $data['id'] ?? ''));
    }
}
