<?php
require_once __DIR__ . '/../config.php';
require_user();

$user = current_user();
$orders = user_orders((int) $user['id']);
$latestCoupon = fetch_latest_coupon_for_user((int) $user['id']);
$title = 'Dashboard';
require __DIR__ . '/header.php';
?>
<div class="row g-3">
    <div class="col-md-4"><div class="card"><div class="card-body"><h6>Total Orders</h6><p class="h4 mb-0"><?= count($orders) ?></p></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><h6>Account</h6><p class="mb-0"><?= e($user['full_name']) ?></p><small><?= e($user['email']) ?></small></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><h6>Latest Late Coupon</h6><?php if ($latestCoupon): ?><p class="mb-1 fw-semibold"><?= e($latestCoupon['code']) ?></p><small>Expires: <?= e($latestCoupon['expires_at']) ?></small><?php else: ?><p class="mb-0 text-muted">No private coupon yet.</p><?php endif; ?></div></div></div>
</div>
<div class="mt-4 row">
    <div class="col-md-3"><?php require __DIR__ . '/sidebar.php'; ?></div>
    <div class="col-md-9">
        <div class="card"><div class="card-body">
            <h5 class="mb-3">Recent Orders</h5>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Code</th><th>Total</th><th>Payment</th><th>Delivery</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($orders, 0, 5) as $order): ?>
                        <tr>
                            <td><?= e($order['order_code']) ?></td>
                            <td>NPR <?= e(money((float) $order['total_amount'])) ?></td>
                            <td><?= e($order['payment_status']) ?></td>
                            <td><?= e($order['delivery_status']) ?></td>
                            <td><a href="<?= e(base_url('/user/order.php?order_code=' . urlencode($order['order_code']))) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
