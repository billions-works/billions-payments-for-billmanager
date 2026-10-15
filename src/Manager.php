<?php

declare(strict_types=1);

require_once __DIR__ . '/Billions.php';

final class BillionsManager
{
    /** @param array<string, string> $parameters */
    public static function query(string $function, array $parameters, ?string $auth = null): SimpleXMLElement
    {
        if (!preg_match('/^payment\.[a-z]+$/', $function)) {
            throw new BillionsError('Invalid BILLmanager function');
        }
        $command = '/usr/local/mgr5/sbin/mgrctl -m billmgr -o xml ' . escapeshellarg($function);
        foreach ($parameters as $key => $value) {
            if (!preg_match('/^[a-z]+$/', $key)) {
                throw new BillionsError('Invalid BILLmanager parameter');
            }
            $command .= ' ' . $key . '=' . escapeshellarg($value);
        }
        if ($auth !== null) {
            $command .= ' auth=' . escapeshellarg($auth);
        }
        $output = [];
        $exit = 0;
        exec($command, $output, $exit);
        $xml = @simplexml_load_string(implode("\n", $output), SimpleXMLElement::class, LIBXML_NONET);
        if ($exit !== 0 || $xml === false || isset($xml->error)) {
            throw new BillionsError('BILLmanager ' . $function . ' failed');
        }
        return $xml;
    }

    /** @return array<string, string> */
    public static function payment(string $id, ?string $auth = null): array
    {
        if (!ctype_digit($id)) {
            throw new BillionsError('Invalid payment ID');
        }
        return BillionsBridge::payment(self::query('payment.info', ['elid' => $id], $auth));
    }

    public static function mark(string $command, string $id, string $invoiceId): void
    {
        if (!in_array($command, ['payment.setinpay', 'payment.setpaid', 'payment.setnopay'], true)
            || !ctype_digit($id) || !preg_match('/^[0-9a-f-]{36}$/i', $invoiceId)) {
            throw new BillionsError('Invalid payment update');
        }
        self::query($command, ['elid' => $id, 'externalid' => $invoiceId]);
    }
}
