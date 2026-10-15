#!/usr/bin/php
<?php

declare(strict_types=1);

require_once '/usr/local/mgr5/include/php/billions-bridge/Manager.php';

function respond(int $status, string $body): void
{
    $phrases = [200 => 'OK', 400 => 'Bad Request', 502 => 'Bad Gateway'];
    echo 'Status: ' . $status . ' ' . ($phrases[$status] ?? 'Error') . "\r\n";
    echo "Content-Type: text/plain; charset=utf-8\r\nCache-Control: no-store\r\n\r\n", $body;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) {
        respond(400, 'Invalid request');
        exit;
    }
    parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $query);
    $id = (string) ($query['elid'] ?? '');
    $body = file_get_contents('php://stdin', false, null, 0, 65537);
    if ($body === false || strlen($body) > 65536) {
        respond(400, 'Invalid request');
        exit;
    }
    $event = json_decode($body, true);
    if (!is_array($event)) {
        respond(400, 'Invalid JSON');
        exit;
    }
    $invoiceId = BillionsBridge::webhookInvoiceId($event);
    $payment = BillionsManager::payment($id);
    if ($payment['externalid'] !== '' && $payment['externalid'] !== $invoiceId) {
        respond(400, 'Invoice mismatch');
        exit;
    }
    $api = new BillionsApi($payment['api_url'] ?: 'https://api.billions.works', $payment['api_key']);
    $invoice = $api->getInvoice($invoiceId);
    BillionsBridge::assertInvoiceMatches($invoice, $payment);
    $status = (string) ($invoice['status'] ?? '');
    if ($status === 'completed') {
        BillionsManager::mark('payment.setpaid', $id, $invoiceId);
    } elseif ($status === 'processing') {
        BillionsManager::mark('payment.setinpay', $id, $invoiceId);
    } elseif ($status === 'expired' || $status === 'failed') {
        BillionsManager::mark('payment.setnopay', $id, $invoiceId);
    }
    respond(200, 'OK');
} catch (Throwable $error) {
    error_log('Billions BILLmanager callback: ' . $error->getMessage());
    respond(502, 'Verification failed');
}
