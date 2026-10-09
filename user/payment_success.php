<?php
require_once __DIR__ . '/../config.php';
require_once APP_ROOT . '/components/payment/stripe.php';
require_once APP_ROOT . '/components/payment/esewa.php';
require_once APP_ROOT . '/components/payment/khalti.php';

require_user();
$input = array_merge($_GET, $_POST);

$orderId = (int) ($input['order'] ?? 0);
$gateway = strtoupper((string) ($input['gateway'] ?? ''));
if ($orderId <= 0 || !in_array($gateway, ['STRIPE', 'ESEWA', 'KHALTI'], true)) {
    flash_error('Invalid payment response.');
    redirect('/user/order.php');
}

assert_order_owner($orderId, (int) $_SESSION['user_id']);
$order = get_order_by_id($orderId);
if (!$order) {
    flash_error('Order not found.');
    redirect('/user/order.php');
}

if ($order['payment_status'] === 'PAID') {
    flash_success('Payment already processed.');
    redirect('/user/order.php?order_code=' . urlencode((string) $order['order_code']));
}

try {
    $verified = null;
    $sessionId = '';
    $tx = '';
    $pidx = '';

    if ($gateway === 'STRIPE') {
        $sessionId = trim((string) ($input['session_id'] ?? ''));
        if ($sessionId === '') {
            throw new RuntimeException('Stripe session id is missing.');
        }
        $verified = stripe_verify_checkout_session($sessionId);
    }

    if ($gateway === 'ESEWA') {
        $tx = trim((string) ($input['transaction_uuid'] ?? ''));
        $amount = trim((string) ($input['total_amount'] ?? ''));

        if ($tx === '' || $amount === '') {
            $dataParam = (string) ($input['data'] ?? '');
            if ($dataParam !== '') {
                $decoded = json_decode((string) base64_decode($dataParam, true), true);
                if (is_array($decoded)) {
                    $tx = (string) ($decoded['transaction_uuid'] ?? '');
                    $amount = (string) ($decoded['total_amount'] ?? '');
                }
            }
        }

        if ($tx === '' || $amount === '') {
            throw new RuntimeException('eSewa response missing required data.');
        }

        $verified = esewa_verify_transaction($tx, $amount);
    }

    if ($gateway === 'KHALTI') {
        $pidx = trim((string) ($input['pidx'] ?? ''));
        if ($pidx === '') {
            throw new RuntimeException('Khalti pidx is missing.');
        }

        $verified = khalti_lookup_payment($pidx);
    }

    if (!$verified || !$verified['verified']) {
        process_payment_failed($orderId, $gateway, 'VERIFY_FAILED', (float) $order['total_amount'], ['gateway' => $gateway, 'query' => $_GET]);
        flash_error('Payment verification failed.');
        redirect('/user/payment_failed.php?gateway=' . urlencode($gateway) . '&order=' . $orderId);
    }

    $verifiedAmount = (float) ($verified['amount'] ?? 0.0);
    $orderAmount = (float) $order['total_amount'];
    if (abs($verifiedAmount - $orderAmount) > 0.01) {
        throw new RuntimeException('Payment amount mismatch. Please contact support.');
    }

    if ($gateway === 'STRIPE') {
        $expectedSession = (string) (($_SESSION['stripe_checkout_sessions'] ?? [])[(string) $orderId] ?? '');
        if ($expectedSession !== '' && !hash_equals($expectedSession, $sessionId)) {
            throw new RuntimeException('Stripe session mismatch for this order.');
        }

        $verifiedOrderId = (int) ($verified['order_id'] ?? 0);
        if ($verifiedOrderId <= 0 || $verifiedOrderId !== $orderId) {
            throw new RuntimeException('Stripe session does not belong to this order.');
        }

        $currency = strtoupper((string) ($verified['currency'] ?? ''));
        if ($currency !== '' && $currency !== 'NPR') {
            throw new RuntimeException('Stripe currency mismatch.');
        }
    }

    if ($gateway === 'ESEWA') {
        $transactionUuid = (string) ($verified['transaction_uuid'] ?? '');
        $expectedTx = (string) (($_SESSION['esewa_transactions'] ?? [])[(string) $orderId] ?? '');
        if ($expectedTx !== '' && !hash_equals($expectedTx, $transactionUuid)) {
            throw new RuntimeException('eSewa transaction mismatch for this order.');
        }
        if ($transactionUuid === '' || !str_starts_with($transactionUuid, 'ESEWA-' . $orderId . '-')) {
            throw new RuntimeException('eSewa transaction mismatch.');
        }
    }

    if ($gateway === 'KHALTI') {
        $expectedPidx = (string) (($_SESSION['khalti_pidx'] ?? [])[(string) $orderId] ?? '');
        if ($expectedPidx !== '' && !hash_equals($expectedPidx, $pidx)) {
            throw new RuntimeException('Khalti payment session mismatch for this order.');
        }

        $purchaseOrderId = trim((string) ($verified['purchase_order_id'] ?? ''));
        if ($purchaseOrderId === '' || $purchaseOrderId !== (string) $order['order_code']) {
            throw new RuntimeException('Khalti payment does not belong to this order.');
        }

        $currency = strtoupper((string) ($verified['currency'] ?? ''));
        if ($currency !== '' && $currency !== 'NPR') {
            throw new RuntimeException('Khalti currency mismatch.');
        }
    }

    $result = process_payment_success($orderId, $gateway, (string) $verified['txn_ref'], $verifiedAmount, (array) $verified['raw']);
    unset($_SESSION['stripe_checkout_sessions'][(string) $orderId], $_SESSION['esewa_transactions'][(string) $orderId], $_SESSION['khalti_pidx'][(string) $orderId]);
    if (($result['status'] ?? '') === 'refund_required') {
        flash_error('Payment captured but stock was insufficient. Admin will process refund.');
    } else {
        flash_success('Payment successful. Order is confirmed.');
    }

    $order = get_order_by_id($orderId);
} catch (Throwable $e) {
    unset($_SESSION['stripe_checkout_sessions'][(string) $orderId], $_SESSION['esewa_transactions'][(string) $orderId], $_SESSION['khalti_pidx'][(string) $orderId]);
    flash_error($e->getMessage());
    redirect('/user/payment_failed.php?gateway=' . urlencode($gateway) . '&order=' . $orderId);
}

$title = 'Payment Success';
require __DIR__ . '/header.php';
?>
<div class="card">
    <div class="card-body">
        <h1 class="h4 text-success">Payment Successful</h1>
        <p>Order Code: <strong><?= e((string) $order['order_code']) ?></strong></p>
        <p>Tracking Number: <strong><?= e((string) ($order['tracking_number'] ?? 'Pending assignment')) ?></strong></p>
        <?php if ((int) ($order['refund_required'] ?? 0) === 1): ?>
            <div class="alert alert-warning">Payment was captured, but item stock became unavailable. Refund is required.</div>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?= e(base_url('/user/order.php?order_code=' . urlencode((string) $order['order_code']))) ?>">View Order</a>
    </div>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
