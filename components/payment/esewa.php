<?php

declare(strict_types=1);

if (!function_exists('esewa_sign')) {
    function esewa_sign(string $message): string
    {
        if (ESEWA_SECRET === '') {
            throw new RuntimeException('eSewa secret is not configured.');
        }

        return base64_encode(hash_hmac('sha256', $message, ESEWA_SECRET, true));
    }
}

if (!function_exists('esewa_build_checkout_payload')) {
    function esewa_build_checkout_payload(array $order): array
    {
        $amount = money((float) $order['total_amount']);
        $tx = 'ESEWA-' . (int) $order['id'] . '-' . time();
        $productCode = ESEWA_MERCHANT_CODE;

        $signedFieldNames = 'total_amount,transaction_uuid,product_code';
        $signatureData = 'total_amount=' . $amount . ',transaction_uuid=' . $tx . ',product_code=' . $productCode;
        $signature = esewa_sign($signatureData);

        return [
            'form_url' => 'https://rc-epay.esewa.com.np/api/epay/main/v2/form',
            'fields' => [
                'amount' => $amount,
                'tax_amount' => '0',
                'total_amount' => $amount,
                'transaction_uuid' => $tx,
                'product_code' => $productCode,
                'product_service_charge' => '0',
                'product_delivery_charge' => '0',
                'success_url' => base_url('/user/payment_success.php?gateway=ESEWA&order=' . (int) $order['id']),
                'failure_url' => base_url('/user/payment_failed.php?gateway=ESEWA&order=' . (int) $order['id']),
                'signed_field_names' => $signedFieldNames,
                'signature' => $signature,
            ],
        ];
    }
}

if (!function_exists('esewa_verify_transaction')) {
    function esewa_verify_transaction(string $transactionUuid, string $totalAmount): array
    {
        $url = 'https://rc-epay.esewa.com.np/api/epay/transaction/status/?product_code=' . rawurlencode(ESEWA_MERCHANT_CODE)
            . '&total_amount=' . rawurlencode($totalAmount)
            . '&transaction_uuid=' . rawurlencode($transactionUuid);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('eSewa verify failed: ' . $err);
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new RuntimeException('Invalid eSewa verification response.');
        }

        $status = strtoupper((string) ($json['status'] ?? ''));
        $verified = in_array($status, ['COMPLETE', 'SUCCESS'], true);

        return [
            'verified' => $verified,
            'txn_ref' => (string) ($json['ref_id'] ?? $transactionUuid),
            'transaction_uuid' => (string) ($json['transaction_uuid'] ?? $transactionUuid),
            'amount' => (float) $totalAmount,
            'currency' => 'NPR',
            'raw' => $json,
        ];
    }
}
