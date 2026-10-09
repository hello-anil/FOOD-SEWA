<?php
require_once __DIR__ . '/../config.php';

require_user();
$input = array_merge($_GET, $_POST);

$orderId = (int) ($input['order'] ?? 0);
$gateway = strtoupper((string) ($input['gateway'] ?? 'UNKNOWN'));

$order = null;
if ($orderId > 0) {
    assert_order_owner($orderId, (int) $_SESSION['user_id']);
    unset($_SESSION['stripe_checkout_sessions'][(string) $orderId], $_SESSION['esewa_transactions'][(string) $orderId], $_SESSION['khalti_pidx'][(string) $orderId]);
    $order = get_order_by_id($orderId);
    if ($order) {
        try {
            process_payment_failed($orderId, in_array($gateway, ['STRIPE', 'ESEWA', 'KHALTI'], true) ? $gateway : 'STRIPE', 'PAYMENT_CANCELLED', (float) $order['total_amount'], ['query' => $_GET]);
        } catch (Throwable $e) {
            // keep page rendering; order may already be marked.
        }
    }
}

$title = 'Payment Failed';
require __DIR__ . '/header.php';
?>
<div class="card">
    <div class="card-body">
        <h1 class="h4 text-danger">Payment Failed</h1>
        <p>Your payment could not be completed.</p>
        <?php if ($order): ?>
            <p>Order code: <strong><?= e($order['order_code']) ?></strong></p>
            <div class="d-flex gap-2">
                <a class="btn btn-primary" href="<?= e(base_url('/user/payment.php?order=' . (int) $order['id'] . '&gateway=' . urlencode($gateway === 'UNKNOWN' ? 'STRIPE' : $gateway))) ?>">Retry Payment</a>
                <a class="btn btn-outline-secondary" href="<?= e(base_url('/user/order.php?order_code=' . urlencode((string) $order['order_code']))) ?>">Back to Order</a>
            </div>
        <?php else: ?>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('/user/order.php')) ?>">Back to Orders</a>
        <?php endif; ?>
    </div>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
