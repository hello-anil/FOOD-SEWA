<?php
require_once __DIR__ . '/../config.php';
require_admin();

if (is_post()) {
    verify_csrf();
    try {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new RuntimeException('Invalid user id.');
        }
        $status = validate_enum(strtoupper((string) ($_POST['status'] ?? 'ACTIVE')), ['ACTIVE', 'DISABLED'], 'Invalid user status.');

        $stmt = db()->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $userId);
        $stmt->execute();
        flash_success('User status updated.');
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }
    redirect('/admin/users.php');
}

$stmt = db()->prepare('SELECT id, full_name, email, phone, status, created_at FROM users ORDER BY id DESC');
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$title = 'Users';
require __DIR__ . '/header.php';
?>
<div class="card"><div class="card-body table-responsive">
    <table class="table table-sm align-middle">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
            <tr>
                <td><?= e($user['full_name']) ?></td>
                <td><?= e($user['email']) ?></td>
                <td><?= e($user['phone']) ?></td>
                <td><?= e($user['status']) ?></td>
                <td>
                    <form method="post" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                        <select name="status" class="form-select form-select-sm">
                            <option value="ACTIVE" <?= $user['status'] === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                            <option value="DISABLED" <?= $user['status'] === 'DISABLED' ? 'selected' : '' ?>>DISABLED</option>
                        </select>
                        <button class="btn btn-sm btn-outline-primary">Save</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
