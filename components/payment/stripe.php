<?php

declare(strict_types=1);

if (!function_exists('stripe_request')) {
    function stripe_request(string $method, string $endpoint, array $payload = []): array
    {
        if (STRIPE_SECRET_KEY === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');
        $ch = curl_init($url);
        $headers = ['Authorization: Bearer ' . STRIPE_SECRET_KEY];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if (!empty($payload)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        }

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Stripe request failed: ' . $err);
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new RuntimeException('Stripe returned invalid JSON.');
        }

        if ($status >= 400) {
            $message = $json['error']['message'] ?? 'Stripe API error';
            throw new RuntimeException($message);
        }

        return $json;
    }
}

if (!function_exists('stripe_create_checkout_session')) {
    function stripe_create_checkout_session(array $order, array $items, ?array $user = null): array
    {
        $orderId = (int) $order['id'];
        $orderCode = (string) ($order['order_code'] ?? ('ORDER-' . $orderId));
        $finalAmount = (float) ($order['total_amount'] ?? 0.0);
        $unitAmount = (int) round($finalAmount * 100);

        if ($unitAmount <= 0) {
            throw new RuntimeException('Stripe payment is unavailable for zero-amount orders.');
        }

        $payload = [
            'mode' => 'payment',
            'success_url' => base_url('/user/payment_success.php?gateway=STRIPE&order=' . $orderId . '&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => base_url('/user/payment_failed.php?gateway=STRIPE&order=' . $orderId),
            'metadata[order_id]' => (string) $orderId,
            'metadata[order_code]' => $orderCode,
            'line_items[0][price_data][currency]' => 'npr',
            'line_items[0][price_data][product_data][name]' => 'FOOD SEWA Order #' . $orderCode,
            'line_items[0][price_data][unit_amount]' => (string) $unitAmount,
            'line_items[0][quantity]' => '1',
        ];

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $payload['customer_email'] = $email;
            $payload['payment_intent_data[receipt_email]'] = $email;
        }

        return stripe_request('POST', '/checkout/sessions', $payload);
    }
}

if (!function_exists('stripe_verify_checkout_session')) {
    function stripe_verify_checkout_session(string $sessionId): array
    {
        $session = stripe_request('GET', '/checkout/sessions/' . rawurlencode($sessionId));
        $paid = (($session['payment_status'] ?? '') === 'paid');
        $metadata = isset($session['metadata']) && is_array($session['metadata']) ? $session['metadata'] : [];

        return [
            'verified' => $paid,
            'txn_ref' => (string) ($session['payment_intent'] ?? $sessionId),
            'amount' => isset($session['amount_total']) ? ((float) $session['amount_total'] / 100) : 0.0,
            'currency' => strtoupper((string) ($session['currency'] ?? '')),
            'order_id' => isset($metadata['order_id']) ? (int) $metadata['order_id'] : 0,
            'raw' => $session,
        ];
    }
}
