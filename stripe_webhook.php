<?php
require_once __DIR__ . '/config.php';
require_once APP_ROOT . '/components/payment/stripe.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

$payload = file_get_contents('php://input');
if (!is_string($payload) || $payload === '') {
    http_response_code(400);
    exit('Missing payload');
}

$signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

try {
    $event = stripe_construct_webhook_event($payload, $signature);
    $type = (string) ($event['type'] ?? '');
    $object = isset($event['data']['object']) && is_array($event['data']['object']) ? $event['data']['object'] : [];
    $metadata = isset($object['metadata']) && is_array($object['metadata']) ? $object['metadata'] : [];

    if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
        $orderId = (int) ($metadata['order_id'] ?? 0);
        $txnRef = (string) ($object['payment_intent'] ?? ($object['id'] ?? ''));
        $amount = isset($object['amount_total']) ? ((float) $object['amount_total'] / 100) : 0.0;
        $paymentStatus = strtolower((string) ($object['payment_status'] ?? ''));

        if ($orderId > 0 && $txnRef !== '' && $amount > 0 && ($paymentStatus === 'paid' || $type === 'checkout.session.async_payment_succeeded')) {
            process_payment_success($orderId, 'STRIPE', $txnRef, $amount, $object);
        }
    }

    if ($type === 'checkout.session.async_payment_failed') {
        $orderId = (int) ($metadata['order_id'] ?? 0);
        $txnRef = (string) ($object['payment_intent'] ?? ($object['id'] ?? 'ASYNC_FAILED'));
        $amount = isset($object['amount_total']) ? ((float) $object['amount_total'] / 100) : 0.0;

        if ($orderId > 0) {
            process_payment_failed($orderId, 'STRIPE', $txnRef, $amount, $object);
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['received' => true], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Stripe webhook error: ' . $e->getMessage());
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'received' => false,
        'message' => APP_DEBUG ? $e->getMessage() : 'Webhook processing failed.',
    ], JSON_UNESCAPED_SLASHES);
}
