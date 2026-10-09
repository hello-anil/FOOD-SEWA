<?php
require_once __DIR__ . '/../config.php';

if (!empty($_SESSION['user_id'])) {
    redirect('/user/dashboard.php');
}

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'register') {
            $name = validate_full_name((string) ($_POST['full_name'] ?? ''));
            $email = validate_email((string) ($_POST['email'] ?? ''));
            $phone = validate_phone((string) ($_POST['phone'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            validate_password_strength($password);

            $registerRateKey = client_ip() . '|' . $email;
            if (!rate_limit_allow('user_register', $registerRateKey, 8, 1800)) {
                throw new RuntimeException('Too many registration attempts. Please try again later.');
            }

            $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                throw new RuntimeException('Email already registered.');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $active = 'ACTIVE';
            $stmt = db()->prepare('INSERT INTO users (full_name, email, phone, password_hash, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->bind_param('sssss', $name, $email, $phone, $hash, $active);
            $stmt->execute();

            $_SESSION['user_id'] = (int) db()->insert_id;
            session_regenerate_id(true);
            mark_auth_session_started();
            rate_limit_reset('user_register', $registerRateKey);
            merge_guest_state_into_user((int) $_SESSION['user_id']);
            flash_success('Registration successful.');
            redirect('/user/dashboard.php');
        }

        if ($action === 'login') {
            $email = validate_email((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $loginRateKey = client_ip() . '|' . $email;
            if (!rate_limit_allow('user_login', $loginRateKey, 7, 900)) {
                throw new RuntimeException('Too many login attempts. Please try again in 15 minutes.');
            }

            $stmt = db()->prepare('SELECT id, password_hash, status FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user || !password_verify($password, (string) $user['password_hash'])) {
                throw new RuntimeException('Invalid login credentials.');
            }

            if ($user['status'] !== 'ACTIVE') {
                throw new RuntimeException('User account is disabled.');
            }

            $_SESSION['user_id'] = (int) $user['id'];
            session_regenerate_id(true);
            mark_auth_session_started();
            rate_limit_reset('user_login', $loginRateKey);
            merge_guest_state_into_user((int) $_SESSION['user_id']);
            flash_success('Welcome back.');
            redirect('/user/dashboard.php');
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
        $mode = $action === 'register' ? 'signup' : 'login';
        redirect('/user/auth.php?mode=' . urlencode($mode));
    }
}

$mode = strtolower(trim((string) ($_GET['mode'] ?? 'login')));
if (!in_array($mode, ['login', 'signup'], true)) {
    $mode = 'login';
}

$isSignup = $mode === 'signup';
$title = $isSignup ? 'Sign Up' : 'Login';
require __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-body">
                <?php if ($isSignup): ?>
                    <h5>Sign Up</h5>
                    <form method="post" class="row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="register">
                        <div class="col-12"><input class="form-control" name="full_name" placeholder="Full name" required></div>
                        <div class="col-12"><input class="form-control" name="email" type="email" placeholder="Email" required></div>
                        <div class="col-12"><input class="form-control" name="phone" placeholder="Phone" required></div>
                        <div class="col-12"><input class="form-control" name="password" type="password" placeholder="Password" required></div>
                        <div class="col-12"><button class="btn btn-success w-100">Create Account</button></div>
                    </form>
                    <p class="small text-center mt-3 mb-0">
                        Already have an account?
                        <a href="<?= e(base_url('/user/auth.php?mode=login')) ?>">Login</a>
                    </p>
                <?php else: ?>
                    <h5>Login</h5>
                    <form method="post" class="row g-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="login">
                        <div class="col-12"><input class="form-control" name="email" type="email" placeholder="Email" required></div>
                        <div class="col-12"><input class="form-control" name="password" type="password" placeholder="Password" required></div>
                        <div class="col-12"><button class="btn btn-primary w-100">Login</button></div>
                    </form>
                    <p class="small text-center mt-3 mb-0">
                        Don’t have an account?
                        <a href="<?= e(base_url('/user/auth.php?mode=signup')) ?>">Sign up</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
