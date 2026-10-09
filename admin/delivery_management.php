<?php
require_once __DIR__ . '/../config.php';
require_admin();

run_late_coupon_scan();

if (is_post()) {
    verify_csrf();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'start') {
            update_delivery_status($orderId, 'OUT_FOR_DELIVERY');
            flash_success('Delivery started.');
        }

        if ($action === 'deliver') {
            update_delivery_status($orderId, 'DELIVERED');
            flash_success('Order marked as delivered.');
        }

        if ($action === 'update_status') {
            $status = strtoupper(trim((string) ($_POST['delivery_status'] ?? '')));
            update_delivery_status($orderId, $status);
            flash_success('Delivery status updated.');
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }

    redirect('/admin/delivery_management.php');
}

$deliveryStatuses = ['PENDING', 'CONFIRMED', 'OUT_FOR_DELIVERY', 'DELIVERED', 'CANCELLED'];

$stmt = db()->prepare("SELECT o.id, o.order_code, o.delivery_status, o.payment_status, o.delivery_start_at, o.delivered_at, o.tracking_number, o.total_amount, o.created_at, u.full_name, u.email
    FROM orders o
    INNER JOIN users u ON u.id = o.user_id
    WHERE o.payment_status = 'PAID'
    ORDER BY o.id DESC");
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$title = 'Delivery Management';
require __DIR__ . '/header.php';
?>
<div class="card"><div class="card-body table-responsive">
    <table class="table table-sm align-middle">
        <thead><tr><th>Order</th><th>User</th><th>Status</th><th>Started At</th><th>Tracking</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= e($order['order_code']) ?></td>
                <td>
                    <div><?= e((string) $order['full_name']) ?></div>
                    <small class="text-muted"><?= e((string) $order['email']) ?></small>
                </td>
                <td><?= e($order['delivery_status']) ?></td>
                <td><?= e((string) ($order['delivery_start_at'] ?? '-')) ?></td>
                <td><?= e((string) ($order['tracking_number'] ?? '-')) ?></td>
                <td class="d-flex gap-2">
                    <?php if ($order['delivery_status'] === 'DELIVERED'): ?>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#deliveryDetailModal<?= (int) $order['id'] ?>">View Details</button>
                    <?php else: ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <input type="hidden" name="action" value="start">
                            <button class="btn btn-sm btn-outline-primary" <?= in_array((string) $order['delivery_status'], ['PENDING', 'CONFIRMED'], true) ? '' : 'disabled' ?>>Start</button>
                        </form>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <input type="hidden" name="action" value="deliver">
                            <button class="btn btn-sm btn-outline-success" <?= $order['delivery_status'] === 'OUT_FOR_DELIVERY' ? '' : 'disabled' ?>>Delivered</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
            <tr><td colspan="6" class="text-center text-muted">No paid orders available for delivery management.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div></div>
<?php foreach ($orders as $order): ?>
    <?php if ($order['delivery_status'] !== 'DELIVERED') {
        continue;
    } ?>
    <div class="modal fade" id="deliveryDetailModal<?= (int) $order['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delivery Details: <?= e((string) $order['order_code']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-1">Customer: <?= e((string) $order['full_name']) ?> (<?= e((string) $order['email']) ?>)</p>
                    <p class="small mb-1">Payment: <?= e((string) $order['payment_status']) ?></p>
                    <p class="small mb-1">Tracking: <?= e((string) ($order['tracking_number'] ?? '-')) ?></p>
                    <p class="small mb-1">Started At: <?= e((string) ($order['delivery_start_at'] ?? '-')) ?></p>
                    <p class="small mb-1">Delivered At: <?= e((string) ($order['delivered_at'] ?? '-')) ?></p>
                    <p class="small mb-2">Total: NPR <?= e(money((float) $order['total_amount'])) ?></p>
                    <p class="small mb-2">All delivery statuses:</p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($deliveryStatuses as $status): ?>
                            <span class="badge <?= $order['delivery_status'] === $status ? 'text-bg-primary' : 'text-bg-light border' ?>"><?= e($status) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <form method="post" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                        <select name="delivery_status" class="form-select form-select-sm">
                            <?php foreach ($deliveryStatuses as $status): ?>
                                <option value="<?= e($status) ?>" <?= $order['delivery_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-primary">Update</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
