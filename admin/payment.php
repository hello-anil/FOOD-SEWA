<?php
require_once __DIR__ . '/../config.php';
require_admin();

$gateway = strtoupper(trim((string) ($_GET['gateway'] ?? '')));
$status = strtoupper(trim((string) ($_GET['status'] ?? '')));

$sql = 'SELECT p.id, p.order_id, p.gateway, p.amount, p.status, p.txn_ref, p.created_at, o.order_code
    FROM payments p
    INNER JOIN orders o ON o.id = p.order_id';
$clauses = [];
$params = [];
$types = '';

if (in_array($gateway, ['STRIPE', 'ESEWA', 'KHALTI'], true)) {
    $clauses[] = 'p.gateway = ?';
    $params[] = $gateway;
    $types .= 's';
}
if (in_array($status, ['SUCCESS', 'FAILED', 'PENDING'], true)) {
    $clauses[] = 'p.status = ?';
    $params[] = $status;
    $types .= 's';
}
if ($clauses) {
    $sql .= ' WHERE ' . implode(' AND ', $clauses);
}
$sql .= ' ORDER BY p.id DESC';

$stmt = db()->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$title = 'Payment Logs';
require __DIR__ . '/header.php';
?>
<form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
        <select class="form-select" name="gateway">
            <option value="">All gateways</option>
            <?php foreach (['STRIPE','ESEWA','KHALTI'] as $g): ?><option value="<?= e($g) ?>" <?= $gateway === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select class="form-select" name="status">
            <option value="">All status</option>
            <?php foreach (['SUCCESS','FAILED','PENDING'] as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>
<div class="card"><div class="card-body table-responsive">
    <table class="table table-sm align-middle">
        <thead><tr><th>ID</th><th>Order</th><th>Gateway</th><th>Status</th><th>Amount</th><th>Txn</th><th>Created</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
            <tr>
                <td><?= (int) $p['id'] ?></td>
                <td><?= e($p['order_code']) ?></td>
                <td><?= e($p['gateway']) ?></td>
                <td><?= e($p['status']) ?></td>
                <td><?= e(money((float) $p['amount'])) ?></td>
                <td><code><?= e($p['txn_ref']) ?></code></td>
                <td><?= e($p['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?>
            <tr><td colspan="7" class="text-center text-muted">No payment records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div></div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
