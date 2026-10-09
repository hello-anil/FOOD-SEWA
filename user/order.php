<?php
require_once __DIR__ . '/../config.php';
require_user();

$userId = (int) $_SESSION['user_id'];
$orderCode = trim((string) ($_GET['order_code'] ?? ''));
$selectedOrder = null;
$items = [];
$tracking = null;
$orderStatus = '';
$statusMessage = '';
$lateCouponPercent = (int) round(late_coupon_discount_percent());

if ($orderCode !== '') {
    try {
        $orderCode = validate_order_code($orderCode);
    } catch (Throwable $e) {
        flash_error($e->getMessage());
        redirect('/user/order.php');
    }

    $selectedOrder = user_order_by_code($userId, $orderCode);
    if (!$selectedOrder) {
        flash_error('Order not found.');
        redirect('/user/order.php');
    }

    $items = order_items((int) $selectedOrder['id']);
    $tracking = tracking_payload($selectedOrder);
    $orderStatus = strtoupper((string) ($tracking['status'] ?? $selectedOrder['delivery_status']));
    if ($orderStatus === 'PENDING') {
        $statusMessage = 'Order placed and waiting for confirmation.';
    } elseif ($orderStatus === 'CONFIRMED') {
        $statusMessage = 'Order confirmed and being prepared. Tracking will start after dispatch.';
    } elseif ($orderStatus === 'OUT_FOR_DELIVERY') {
        $statusMessage = 'Rider is on the way. Live progress is now active.';
    } elseif ($orderStatus === 'DELIVERED') {
        $statusMessage = 'Order delivered successfully.';
    } elseif ($orderStatus === 'CANCELLED') {
        $statusMessage = 'Order was cancelled. Please contact support if needed.';
    }
}

$orders = user_orders($userId);
$title = 'Orders';
require __DIR__ . '/header.php';
?>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6>Your Orders</h6>
                <div class="list-group">
                    <?php foreach ($orders as $order): ?>
                        <a class="list-group-item list-group-item-action" href="<?= e(base_url('/user/order.php?order_code=' . urlencode($order['order_code']))) ?>">
                            <div class="d-flex justify-content-between"><span><?= e($order['order_code']) ?></span><small><?= e($order['delivery_status']) ?></small></div>
                            <small>NPR <?= e(money((float) $order['total_amount'])) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <?php if ($selectedOrder): ?>
            <div class="card mb-3"><div class="card-body">
                <h5>Order <?= e($selectedOrder['order_code']) ?></h5>
                <p class="mb-1">Payment: <?= e($selectedOrder['payment_status']) ?></p>
                <p class="mb-1">Delivery: <?= e($selectedOrder['delivery_status']) ?></p>
                <p class="mb-1">Tracking: <?= e((string) ($selectedOrder['tracking_number'] ?? '-')) ?></p>
                <p class="mb-1">Total: NPR <?= e(money((float) $selectedOrder['total_amount'])) ?></p>
                <?php if ($statusMessage !== ''): ?>
                    <p class="mb-1"><?= e($statusMessage) ?></p>
                <?php endif; ?>
                <?php if ((int) $selectedOrder['refund_required'] === 1): ?>
                    <div class="alert alert-warning mb-1">Refund required: <?= e((string) $selectedOrder['refund_note']) ?></div>
                <?php endif; ?>
                <?php if ($tracking && strtoupper((string) $selectedOrder['delivery_status']) === 'OUT_FOR_DELIVERY'): ?>
                    <p class="mb-1">Progress: <span id="rt-progress"><?= (int) round($tracking['progress'] * 100) ?></span>%</p>
                    <p class="mb-1">ETA: <span id="rt-eta"><?= $tracking['eta_minutes'] !== null ? (int) $tracking['eta_minutes'] . ' mins' : 'N/A' ?></span></p>
                    <p class="mb-1 text-muted small">If eligible for delayed-delivery rewards, claim <?= (int) $lateCouponPercent ?>% off from tracking page (valid above NPR <?= e(money((float) LATE_COUPON_MIN_ORDER_AMOUNT)) ?>).</p>
                    <p id="rt-coupon-note" class="mb-1 small text-success" style="display:none;">Coupon claim is now available on the tracking page.</p>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/track.php?tracking_number=' . urlencode((string) $selectedOrder['tracking_number']))) ?>">Public Track</a>
            </div></div>

            <div class="card"><div class="card-body">
                <h6>Items</h6>
                <ul class="list-group list-group-flush">
                    <?php foreach ($items as $item): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><?= e($item['name_snapshot']) ?> x <?= (int) $item['qty'] ?></span>
                            <span>NPR <?= e(money((float) $item['price_snapshot'] * (int) $item['qty'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div></div>
        <?php else: ?>
            <div class="alert alert-secondary">Select an order to view details.</div>
        <?php endif; ?>
    </div>
</div>
</main>
<?php if ($selectedOrder): ?>
<script>
const orderCode = <?= json_encode($selectedOrder['order_code']) ?>;
const orderDeliveryStatus = <?= json_encode((string) $selectedOrder['delivery_status']) ?>;
let couponShown = false;
if (orderCode && orderDeliveryStatus === 'OUT_FOR_DELIVERY') {
  setInterval(async () => {
    try {
      const res = await fetch(<?= json_encode(base_url('/user/track_order_realtime.php')) ?> + '?order_code=' + encodeURIComponent(orderCode), {cache: 'no-store'});
      if (!res.ok) return;
      const data = await res.json();
      const progress = document.getElementById('rt-progress');
      const eta = document.getElementById('rt-eta');
      if (progress) progress.textContent = String(Math.round((data.progress || 0) * 100));
      if (eta) eta.textContent = data.eta_minutes !== null ? String(data.eta_minutes) + ' mins' : 'N/A';
      if (data.late_coupon_claimable && !couponShown) {
        couponShown = true;
        const note = document.getElementById('rt-coupon-note');
        if (note) note.style.display = '';
      }
    } catch (e) {}
  }, 5000);
}
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
