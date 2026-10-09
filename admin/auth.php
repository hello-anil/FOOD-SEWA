<?php
require_once __DIR__ . '/../config.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('/admin/dashboard.php');
}

if (is_post()) {
    verify_csrf();

    try {
        $email = validate_email((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $rateKey = client_ip() . '|' . $email;

        if (!rate_limit_allow('admin_login', $rateKey, 6, 900)) {
            throw new RuntimeException('Too many login attempts. Please try again in 15 minutes.');
        }

        $stmt = db()->prepare('SELECT id, password_hash, status FROM admins WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();

        if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
            throw new RuntimeException('Invalid admin credentials.');
        }

        if ($admin['status'] !== 'ACTIVE') {
            throw new RuntimeException('Admin account is disabled.');
        }

        $_SESSION['admin_id'] = (int) $admin['id'];
        session_regenerate_id(true);
        mark_auth_session_started();
        rate_limit_reset('admin_login', $rateKey);
        flash_success('Welcome admin.');
        redirect('/admin/dashboard.php');
    } catch (Throwable $e) {
        flash_error($e->getMessage());
        redirect('/admin/auth.php');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(base_url('/assets/css/admin-theme.css')) ?>" rel="stylesheet">
</head>
<body class="admin-app admin-auth">
<div class="container py-5 admin-auth-shell">
    <div class="card admin-auth-card">
        <div class="card-body">
            <h4 class="mb-3">Admin Login</h4>
            <?php render_flash(); ?>
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <div class="col-12"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
                <div class="col-12"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
                <div class="col-12"><button class="btn btn-primary w-100">Login</button></div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
