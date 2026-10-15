#!/usr/bin/php
<?php

declare(strict_types=1);

require_once '/usr/local/mgr5/include/php/billions-bridge/Manager.php';

function respond(int $status, string $body, ?string $location = null): void
{
    $phrases = [200 => 'OK', 302 => 'Found', 400 => 'Bad Request', 403 => 'Forbidden', 502 => 'Bad Gateway'];
    echo 'Status: ' . $status . ' ' . ($phrases[$status] ?? 'Error') . "\r\n";
    if ($location !== null) {
        echo 'Location: ' . $location . "\r\n";
    }
    echo "Content-Type: text/plain; charset=utf-8\r\nCache-Control: no-store\r\n\r\n", $body;
}

try {
    parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $query);
    $id = (string) ($query['elid'] ?? '');
    $auth = (string) ($query['auth'] ?? $_COOKIE['billmgrses5'] ?? '');
    if (!isset($query['auth'])) {
        $auth = explode(':', $auth, 2)[0];
    }
    if ($auth === '') {
        respond(403, 'BILLmanager session required');
        exit;
    }
    $payment = BillionsManager::payment($id, $auth);
    $api = new BillionsApi($payment['api_url'] ?: 'https://api.billions.works', $payment['api_key']);
    $invoice = $payment['externalid'] !== ''
        ? $api->getInvoice($payment['externalid'])
        : $api->createInvoice(BillionsBridge::createPayload($payment));
    BillionsBridge::assertInvoiceMatches($invoice, $payment);
    $invoiceId = (string) ($invoice['id'] ?? '');
    $url = (string) ($invoice['invoice_url'] ?? '');
    $parts = parse_url($url);
    if (!preg_match('/^[0-9a-f-]{36}$/i', $invoiceId) || !is_array($parts)
        || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
        || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\r\n]/', $url)) {
        throw new BillionsError('Invalid invoice response');
    }
    $status = (string) ($invoice['status'] ?? '');
    if ($status === 'completed') {
        BillionsManager::mark('payment.setpaid', $id, $invoiceId);
    } elseif ($status === 'pending' || $status === 'processing') {
        BillionsManager::mark('payment.setinpay', $id, $invoiceId);
    } else {
        throw new BillionsError('Invoice is no longer payable');
    }
    respond(302, 'Redirecting to Billions checkout', $url);
} catch (Throwable $error) {
    error_log('Billions BILLmanager payment: ' . $error->getMessage());
    respond(502, 'Unable to open the payment page. Please try again later.');
}
