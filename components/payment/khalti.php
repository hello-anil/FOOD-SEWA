<?php

declare(strict_types=1);

if (!function_exists('khalti_request')) {
    function khalti_request(string $endpoint, array $payload): array
    {
        if (KHALTI_SECRET_KEY === '') {
            throw new RuntimeException('Khalti secret key is not configured.');
        }

        $url = 'https://dev.khalti.com/api/v2/' . ltrim($endpoint, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Authorization: Key ' . KHALTI_SECRET_KEY,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Khalti request failed: ' . $err);
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new RuntimeException('Invalid Khalti response.');
        }

        if ($status >= 400) {
            $detail = $json['detail'] ?? 'Khalti API error';
            if (is_array($detail)) {
                $detail = implode('; ', array_map(static fn($x): string => (string) $x, $detail));
            }
            throw new RuntimeException((string) $detail);
        }

        return $json;
    }
}

if (!function_exists('khalti_initiate_payment')) {
    function khalti_initiate_payment(array $order, array $user): array
    {
        $amountPaisa = (int) round((float) $order['total_amount'] * 100);

        $payload = [
            'return_url' => base_url('/user/payment_success.php?gateway=KHALTI&order=' . (int) $order['id']),
            'website_url' => APP_BASE_URL,
            'amount' => $amountPaisa,
            'purchase_order_id' => (string) $order['order_code'],
            'purchase_order_name' => 'Food order #' . (string) $order['order_code'],
            'customer_info' => [
                'name' => (string) ($user['full_name'] ?? 'Customer'),
                'email' => (string) ($user['email'] ?? 'customer@example.com'),
                'phone' => (string) ($user['phone'] ?? '9800000000'),
            ],
        ];

        return khalti_request('/epayment/initiate/', $payload);
    }
}

if (!function_exists('khalti_lookup_payment')) {
    function khalti_lookup_payment(string $pidx): array
    {
        $response = khalti_request('/epayment/lookup/', ['pidx' => $pidx]);
        $status = strtoupper((string) ($response['status'] ?? ''));

        return [
            'verified' => $status === 'COMPLETED',
            'txn_ref' => (string) ($response['transaction_id'] ?? $pidx),
            'amount' => isset($response['total_amount']) ? ((float) $response['total_amount'] / 100) : 0.0,
            'currency' => strtoupper((string) ($response['currency'] ?? 'NPR')),
            'purchase_order_id' => (string) ($response['purchase_order_id'] ?? ''),
            'raw' => $response,
        ];
    }
}
