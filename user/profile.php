<?php
require_once __DIR__ . '/../config.php';
require_user();

$userId = (int) $_SESSION['user_id'];

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_profile') {
            $name = validate_person_name((string) ($_POST['full_name'] ?? ''));
            $phone = validate_phone((string) ($_POST['phone'] ?? ''));

            $stmt = db()->prepare('UPDATE users SET full_name = ?, phone = ? WHERE id = ?');
            $stmt->bind_param('ssi', $name, $phone, $userId);
            $stmt->execute();
            flash_success('Profile updated.');
        }

        if ($action === 'change_password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            validate_password_strength($newPassword);
            if ($current === '') {
                throw new RuntimeException('Current password is required.');
            }
            if (hash_equals($current, $newPassword)) {
                throw new RuntimeException('New password must be different from current password.');
            }

            $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row || !password_verify($current, (string) $row['password_hash'])) {
                throw new RuntimeException('Current password is incorrect.');
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->bind_param('si', $hash, $userId);
            $stmt->execute();
            flash_success('Password changed.');
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }

    redirect('/user/profile.php');
}

$user = current_user();
$title = 'Profile';
require __DIR__ . '/header.php';
?>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card"><div class="card-body">
            <h6>Profile Info</h6>
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">
                <div class="col-12"><input class="form-control" name="full_name" value="<?= e($user['full_name']) ?>" required></div>
                <div class="col-12"><input class="form-control" name="email" value="<?= e($user['email']) ?>" disabled></div>
                <div class="col-12"><input class="form-control" name="phone" value="<?= e($user['phone']) ?>" required></div>
                <div class="col-12"><button class="btn btn-primary w-100">Update Profile</button></div>
            </form>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-body">
            <h6>Change Password</h6>
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <div class="col-12"><input class="form-control" type="password" name="current_password" placeholder="Current password" required></div>
                <div class="col-12"><input class="form-control" type="password" name="new_password" placeholder="New password" required></div>
                <div class="col-12"><button class="btn btn-warning w-100">Change Password</button></div>
            </form>
        </div></div>
    </div>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
