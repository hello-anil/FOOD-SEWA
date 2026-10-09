<?php
require_once __DIR__ . '/../config.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
            $discount = (float) ($_POST['discount_percent'] ?? 0);
            $maxUses = (int) ($_POST['max_uses'] ?? 1);
            $expiresAt = trim((string) ($_POST['expires_at'] ?? ''));
            $expiresAt = str_replace('T', ' ', $expiresAt);
            $status = validate_enum(strtoupper((string) ($_POST['status'] ?? 'ACTIVE')), ['ACTIVE', 'DISABLED'], 'Invalid coupon status.');
            $type = 'GLOBAL';

            if (!preg_match('/^[A-Z0-9_-]{4,30}$/', $code)) {
                throw new RuntimeException('Coupon code must be 4-30 characters (A-Z, 0-9, _ or -).');
            }
            if ($discount <= 0 || $discount > 100) {
                throw new RuntimeException('Discount percent must be greater than 0 and up to 100.');
            }
            if ($maxUses < 1 || $maxUses > 100000) {
                throw new RuntimeException('Max uses must be between 1 and 100000.');
            }

            $expiresTs = strtotime($expiresAt);
            if ($expiresTs === false) {
                throw new RuntimeException('Invalid coupon expiry date.');
            }
            if ($expiresTs <= time()) {
                throw new RuntimeException('Coupon expiry must be a future date.');
            }

            $stmt = db()->prepare('INSERT INTO coupons (code, discount_percent, max_uses, used_count, user_id, expires_at, status, type, created_at)
                VALUES (?, ?, ?, 0, NULL, ?, ?, ?, NOW())');
            $stmt->bind_param('sdisss', $code, $discount, $maxUses, $expiresAt, $status, $type);
            $stmt->execute();
            flash_success('Coupon created.');
        }

        if ($action === 'toggle') {
            $id = (int) $_POST['id'];
            $status = validate_enum(strtoupper((string) $_POST['status']), ['ACTIVE', 'DISABLED'], 'Invalid coupon status.');
            if ($id <= 0) {
                throw new RuntimeException('Invalid coupon id.');
            }
            $stmt = db()->prepare('UPDATE coupons SET status = ? WHERE id = ?');
            $stmt->bind_param('si', $status, $id);
            $stmt->execute();
            flash_success('Coupon status changed.');
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }

    redirect('/admin/coupons.php');
}

$stmt = db()->prepare('SELECT c.*, u.full_name FROM coupons c LEFT JOIN users u ON u.id = c.user_id ORDER BY c.id DESC');
$stmt->execute();
$coupons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$title = 'Coupons';
require __DIR__ . '/header.php';
?>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <h6>Create Global Coupon</h6>
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="col-12"><input class="form-control" name="code" placeholder="Code" required></div>
                <div class="col-6"><input class="form-control" type="number" step="0.01" name="discount_percent" placeholder="Discount %" required></div>
                <div class="col-6"><input class="form-control" type="number" name="max_uses" value="1" required></div>
                <div class="col-12"><input class="form-control" type="datetime-local" name="expires_at" required></div>
                <div class="col-12"><select class="form-select" name="status"><option value="ACTIVE">ACTIVE</option><option value="DISABLED">DISABLED</option></select></div>
                <div class="col-12"><button class="btn btn-primary w-100">Create</button></div>
            </form>
        </div></div>
    </div>
    <div class="col-md-8">
        <div class="card"><div class="card-body table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Code</th><th>Type</th><th>Discount</th><th>Usage</th><th>User</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($coupons as $c): ?>
                    <tr>
                        <td><?= e($c['code']) ?></td>
                        <td><?= e($c['type']) ?></td>
                        <td><?= e((string) $c['discount_percent']) ?>%</td>
                        <td><?= (int) $c['used_count'] ?>/<?= (int) $c['max_uses'] ?></td>
                        <td><?= e((string) ($c['full_name'] ?? 'GLOBAL')) ?></td>
                        <td><?= e($c['status']) ?></td>
                        <td>
                            <form method="post" class="d-flex gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <input type="hidden" name="status" value="<?= $c['status'] === 'ACTIVE' ? 'DISABLED' : 'ACTIVE' ?>">
                                <button class="btn btn-sm btn-outline-primary"><?= $c['status'] === 'ACTIVE' ? 'Disable' : 'Enable' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
