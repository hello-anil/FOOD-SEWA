<?php
require_once __DIR__ . '/../config.php';
require_user();

$userId = (int) $_SESSION['user_id'];

$claimedStmt = db()->prepare('SELECT id, code, discount_percent, status, type, max_uses, used_count, expires_at, created_at
    FROM coupons
    WHERE user_id = ?
    ORDER BY id DESC');
$claimedStmt->bind_param('i', $userId);
$claimedStmt->execute();
$claimedCoupons = $claimedStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$claimableStmt = db()->prepare('SELECT o.id, o.order_code, o.tracking_number, o.total_amount, o.delivery_status, o.delivery_start_at,
        o.delivered_at, o.late_coupon_generated,
        a.lat, a.lng
    FROM orders o
    INNER JOIN addresses a ON a.id = o.address_id
    WHERE o.user_id = ?
    ORDER BY o.id DESC');
$claimableStmt->bind_param('i', $userId);
$claimableStmt->execute();
$orderRows = $claimableStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$claimableOrders = [];
foreach ($orderRows as $row) {
    if (late_coupon_claimable_for_order($row)) {
        $payload = tracking_payload($row);
        $claimableOrders[] = [
            'id' => (int) $row['id'],
            'order_code' => (string) $row['order_code'],
            'tracking_number' => (string) ($row['tracking_number'] ?? ''),
            'total_amount' => (float) ($row['total_amount'] ?? 0),
            'late_coupon_discount_percent' => (float) ($payload['late_coupon_discount_percent'] ?? late_coupon_discount_percent()),
            'coupon_due_at' => (string) ($payload['coupon_due_at'] ?? ''),
        ];
    }
}

$title = 'My Coupons';
require __DIR__ . '/header.php';
?>
<div class="row g-3">
    <div class="col-md-3"><?php require __DIR__ . '/sidebar.php'; ?></div>
    <div class="col-md-9">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="mb-2">Coupons To Claim</h5>
                <p class="text-muted small mb-3">These orders are delayed and eligible for claim under the threshold rule engine.</p>
                <?php if (!$claimableOrders): ?>
                    <div class="alert alert-secondary mb-0">No claimable coupons right now.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Tracking</th>
                                    <th>Total</th>
                                    <th>Discount</th>
                                    <th>Eligible Since</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($claimableOrders as $row): ?>
                                    <tr>
                                        <td><?= e($row['order_code']) ?></td>
                                        <td><?= e($row['tracking_number']) ?></td>
                                        <td>NPR <?= e(money($row['total_amount'])) ?></td>
                                        <td><?= e((string) round($row['late_coupon_discount_percent'], 2)) ?>%</td>
                                        <td><?= $row['coupon_due_at'] !== '' ? e($row['coupon_due_at']) : '-' ?></td>
                                        <td>
                                            <?php if ($row['tracking_number'] !== ''): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/track.php?tracking_number=' . urlencode($row['tracking_number']))) ?>">Claim Now</a>
                                            <?php else: ?>
                                                <span class="text-muted small">Tracking unavailable</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-2">Claimed Coupons</h5>
                <p class="text-muted small mb-3">Coupons already generated for your account.</p>
                <?php if (!$claimedCoupons): ?>
                    <div class="alert alert-secondary mb-0">No coupons claimed yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Discount</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Uses</th>
                                    <th>Expires At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($claimedCoupons as $coupon): ?>
                                    <tr>
                                        <td><strong><?= e((string) $coupon['code']) ?></strong></td>
                                        <td><?= e((string) round((float) $coupon['discount_percent'], 2)) ?>%</td>
                                        <td><?= e((string) $coupon['type']) ?></td>
                                        <td><?= e((string) $coupon['status']) ?></td>
                                        <td><?= (int) $coupon['used_count'] ?>/<?= (int) $coupon['max_uses'] ?></td>
                                        <td><?= !empty($coupon['expires_at']) ? e((string) $coupon['expires_at']) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
