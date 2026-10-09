<?php
require_once __DIR__ . '/../config.php';
require_admin();

run_late_coupon_scan();
$stats = admin_dashboard_stats();

$lateStmt = db()->prepare("SELECT o.id, o.order_code, o.delivery_start_at, TIMESTAMPDIFF(MINUTE, o.delivery_start_at, NOW()) AS elapsed_minutes, u.full_name
    FROM orders o
    INNER JOIN users u ON u.id = o.user_id
    WHERE o.delivery_status = 'OUT_FOR_DELIVERY' AND o.delivery_start_at IS NOT NULL AND TIMESTAMPDIFF(MINUTE, o.delivery_start_at, NOW()) > ?");
$limit = DELIVERY_TIME_LIMIT_MINUTES;
$lateStmt->bind_param('i', $limit);
$lateStmt->execute();
$lateOrders = $lateStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$refundStmt = db()->prepare('SELECT id, order_code, refund_note, created_at FROM orders WHERE refund_required = 1 ORDER BY id DESC');
$refundStmt->execute();
$refundOrders = $refundStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$title = 'Dashboard';
require __DIR__ . '/header.php';
?>
<div class="row g-3 mb-4">
    <div class="col-md-2"><div class="card"><div class="card-body"><small>Total Orders</small><div class="h5 mb-0"><?= (int) $stats['total_orders'] ?></div></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body"><small>Paid</small><div class="h5 mb-0"><?= (int) $stats['paid_orders'] ?></div></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body"><small>Failed</small><div class="h5 mb-0"><?= (int) $stats['failed_orders'] ?></div></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body"><small>Revenue</small><div class="h5 mb-0">NPR <?= e(money((float) $stats['revenue'])) ?></div></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body"><small>Out for Delivery</small><div class="h5 mb-0"><?= (int) $stats['out_for_delivery'] ?></div></div></div></div>
    <div class="col-md-2"><div class="card"><div class="card-body"><small>Delivered</small><div class="h5 mb-0"><?= (int) $stats['delivered'] ?></div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card"><div class="card-body">
            <h6>Late Deliveries</h6>
            <ul class="list-group list-group-flush">
                <?php foreach ($lateOrders as $order): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span><?= e($order['order_code']) ?> - <?= e($order['full_name']) ?></span>
                        <span><?= (int) $order['elapsed_minutes'] ?> min</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-body">
            <h6>Refund Required Orders</h6>
            <ul class="list-group list-group-flush">
                <?php foreach ($refundOrders as $order): ?>
                    <li class="list-group-item">
                        <div class="fw-semibold"><?= e($order['order_code']) ?></div>
                        <small><?= e((string) $order['refund_note']) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div></div>
    </div>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
