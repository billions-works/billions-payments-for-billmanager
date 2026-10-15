<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Billions.php';

$xml = simplexml_load_string('<doc><payment><id>42</id><paymethodamount>10.00</paymethodamount><description>Test</description><externalid></externalid><paymethod>7</paymethod><paymethod><id>7</id><module>pmbillions</module><api_key>key</api_key><instance_id>prod_1</instance_id><callback_base_url>https://billing.example.com</callback_base_url></paymethod><currency>1</currency><currency><iso>USD</iso></currency></payment></doc>');
assert($xml instanceof SimpleXMLElement);
$payment = BillionsBridge::payment($xml);
$payload = BillionsBridge::createPayload($payment);
if ($payload['order_no'] !== 'bm-prod_1-7-42'
    || $payload['idempotencyKey'] !== $payload['order_no']
    || $payload['callbackUrl'] !== 'https://billing.example.com/mancgi/billionsresult.php?elid=42') {
    throw new RuntimeException('Incorrect invoice request');
}
$invoice = ['id' => '11111111-1111-4111-8111-111111111111', 'order_no' => $payload['order_no'], 'settlement_currency' => 'USD', 'amount' => '10'];
BillionsBridge::assertInvoiceMatches($invoice, $payment);
$invoice['amount'] = '11';
try {
    BillionsBridge::assertInvoiceMatches($invoice, $payment);
    throw new RuntimeException('Amount mismatch was accepted');
} catch (BillionsError $expected) {
}
$invoice['amount'] = '10.00';
$invoice['order_no'] = 'bm-other-7-42';
try {
    BillionsBridge::assertInvoiceMatches($invoice, $payment);
    throw new RuntimeException('Order mismatch was accepted');
} catch (BillionsError $expected) {
}
$invoice['order_no'] = $payload['order_no'];
$payment['externalid'] = '22222222-2222-4222-8222-222222222222';
try {
    BillionsBridge::assertInvoiceMatches($invoice, $payment);
    throw new RuntimeException('External invoice mismatch was accepted');
} catch (BillionsError $expected) {
}
if (BillionsBridge::webhookInvoiceId(['data' => ['invoice_id' => $invoice['id']]]) !== $invoice['id']) {
    throw new RuntimeException('Webhook invoice ID was not found');
}
echo "Bridge tests passed\n";
