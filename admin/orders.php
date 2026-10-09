<?php
require_once __DIR__ . '/../config.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $status = strtoupper((string) ($_POST['delivery_status'] ?? ''));

    try {
        if ($orderId <= 0) {
            throw new RuntimeException('Invalid order.');
        }

        update_delivery_status($orderId, $status);
        flash_success('Delivery status updated.');
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }

    redirect('/admin/orders.php');
}

$statusFilter = strtoupper(trim((string) ($_GET['status'] ?? '')));
if ($statusFilter !== '' && !in_array($statusFilter, ['PENDING', 'CONFIRMED', 'OUT_FOR_DELIVERY', 'DELIVERED', 'CANCELLED'], true)) {
    $statusFilter = '';
}

if ($statusFilter !== '') {
    $stmt = db()->prepare('SELECT o.id, o.order_code, o.total_amount, o.payment_status, o.delivery_status, o.tracking_number, o.created_at, u.full_name, u.email
        FROM orders o
        INNER JOIN users u ON u.id = o.user_id
        WHERE o.delivery_status = ?
        ORDER BY o.id DESC');
    $stmt->bind_param('s', $statusFilter);
} else {
    $stmt = db()->prepare('SELECT o.id, o.order_code, o.total_amount, o.payment_status, o.delivery_status, o.tracking_number, o.created_at, u.full_name, u.email
        FROM orders o
        INNER JOIN users u ON u.id = o.user_id
        ORDER BY o.id DESC');
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$orderDetail = null;
$orderItems = [];
if (!empty($_GET['id'])) {
    $detailId = (int) $_GET['id'];
    $detailStmt = db()->prepare('SELECT
            o.id, o.order_code, o.total_amount, o.payment_status, o.delivery_status, o.tracking_number, o.created_at,
            o.delivery_start_at, o.delivered_at, o.refund_required, o.refund_note, o.discount_percent, o.discount_amount,
            u.full_name AS user_full_name, u.email AS user_email, u.phone AS user_phone, u.status AS user_status,
            a.full_name AS addr_full_name, a.phone AS addr_phone,
            COALESCE(NULLIF(a.address_name, \'\'), \'Home\') AS address_name,
            COALESCE(NULLIF(a.address_line_1, \'\'), a.address_line) AS address_line_1,
            COALESCE(a.address_line_2, a.landmark) AS address_line_2,
            a.address_line, a.city, COALESCE(NULLIF(a.zone, \'\'), a.area) AS zone,
            a.area, a.landmark, a.lat, a.lng, a.created_at AS address_created_at
        FROM orders o
        INNER JOIN users u ON u.id = o.user_id
        INNER JOIN addresses a ON a.id = o.address_id
        WHERE o.id = ? LIMIT 1');
    $detailStmt->bind_param('i', $detailId);
    $detailStmt->execute();
    $orderDetail = $detailStmt->get_result()->fetch_assoc() ?: null;
    if ($orderDetail) {
        $orderItems = order_items($detailId);
    }
}

$title = 'Orders';
require __DIR__ . '/header.php';
?>
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <form method="get" class="d-flex gap-2">
            <select name="status" class="form-select">
                <option value="">All status</option>
                <?php foreach (['PENDING','CONFIRMED','OUT_FOR_DELIVERY','DELIVERED','CANCELLED'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-primary">Filter</button>
        </form>
    </div>
</div>
<div class="row g-3">
    <div class="col-md-8">
        <div class="card"><div class="card-body table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Code</th><th>User</th><th>Payment</th><th>Delivery</th><th>Total</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= e($order['order_code']) ?></td>
                        <td><?= e($order['full_name']) ?></td>
                        <td><?= e($order['payment_status']) ?></td>
                        <td><?= e($order['delivery_status']) ?></td>
                        <td>NPR <?= e(money((float) $order['total_amount'])) ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/admin/orders.php?id=' . (int) $order['id'])) ?>">Details</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div>
    <div class="col-md-4">
        <?php if ($orderDetail): ?>
            <div class="card"><div class="card-body">
                <h6>Order <?= e($orderDetail['order_code']) ?></h6>
                <p class="small mb-1">Tracking: <?= e((string) ($orderDetail['tracking_number'] ?? '-')) ?></p>
                <p class="small mb-1">Payment: <?= e((string) $orderDetail['payment_status']) ?></p>
                <p class="small mb-1">Delivery: <?= e((string) $orderDetail['delivery_status']) ?></p>
                <p class="small mb-2">Placed: <?= e((string) $orderDetail['created_at']) ?></p>

                <hr class="my-2">
                <h6 class="small text-uppercase text-muted mb-2">Customer</h6>
                <p class="small mb-1"><strong>Name:</strong> <?= e((string) $orderDetail['user_full_name']) ?></p>
                <p class="small mb-1"><strong>Email:</strong> <?= e((string) $orderDetail['user_email']) ?></p>
                <p class="small mb-1"><strong>Phone:</strong> <?= e((string) $orderDetail['user_phone']) ?></p>
                <p class="small mb-2"><strong>Account:</strong> <?= e((string) $orderDetail['user_status']) ?></p>

                <hr class="my-2">
                <h6 class="small text-uppercase text-muted mb-2">Delivery Address</h6>
                <p class="small mb-1"><strong>Address Name:</strong> <?= e((string) $orderDetail['address_name']) ?></p>
                <p class="small mb-1"><strong>Receiver:</strong> <?= e((string) $orderDetail['addr_full_name']) ?></p>
                <p class="small mb-1"><strong>Receiver Phone:</strong> <?= e((string) $orderDetail['addr_phone']) ?></p>
                <p class="small mb-1"><strong>Line 1:</strong> <?= e((string) $orderDetail['address_line_1']) ?></p>
                <?php if (trim((string) ($orderDetail['address_line_2'] ?? '')) !== ''): ?>
                    <p class="small mb-1"><strong>Line 2:</strong> <?= e((string) $orderDetail['address_line_2']) ?></p>
                <?php endif; ?>
                <p class="small mb-1"><strong>City/Zone:</strong> <?= e((string) $orderDetail['city']) ?>, <?= e((string) $orderDetail['zone']) ?></p>
                <?php if ($orderDetail['lat'] !== null && $orderDetail['lng'] !== null): ?>
                    <p class="small mb-2"><strong>Pinned:</strong> <?= e((string) $orderDetail['lat']) ?>, <?= e((string) $orderDetail['lng']) ?></p>
                <?php endif; ?>

                <hr class="my-2">
                <h6 class="small text-uppercase text-muted mb-2">Order Timeline</h6>
                <p class="small mb-1"><strong>Out For Delivery At:</strong> <?= e((string) ($orderDetail['delivery_start_at'] ?? '-')) ?></p>
                <p class="small mb-2"><strong>Delivered At:</strong> <?= e((string) ($orderDetail['delivered_at'] ?? '-')) ?></p>
                <p class="small mb-1"><strong>Discount:</strong> <?= e((string) $orderDetail['discount_percent']) ?>% (NPR <?= e(money((float) $orderDetail['discount_amount'])) ?>)</p>
                <p class="small mb-2"><strong>Total:</strong> NPR <?= e(money((float) $orderDetail['total_amount'])) ?></p>
                <?php if ((int) ($orderDetail['refund_required'] ?? 0) === 1): ?>
                    <div class="alert alert-warning py-2 small mb-2">
                        Refund required: <?= e((string) ($orderDetail['refund_note'] ?? '')) ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="d-flex gap-2 mb-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int) $orderDetail['id'] ?>">
                    <select name="delivery_status" class="form-select form-select-sm">
                        <?php foreach (['PENDING','CONFIRMED','OUT_FOR_DELIVERY','DELIVERED','CANCELLED'] as $s): ?>
                            <option value="<?= e($s) ?>" <?= $orderDetail['delivery_status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-primary">Update</button>
                </form>
                <ul class="list-group list-group-flush">
                    <?php foreach ($orderItems as $item): ?>
                        <li class="list-group-item d-flex justify-content-between px-0"><span><?= e($item['name_snapshot']) ?> x <?= (int) $item['qty'] ?></span><span>NPR <?= e(money((float) $item['price_snapshot'] * (int) $item['qty'])) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div></div>
        <?php else: ?>
            <div class="alert alert-secondary">Select an order to inspect details.</div>
        <?php endif; ?>
    </div>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
