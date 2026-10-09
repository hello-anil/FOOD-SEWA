<?php
require_once __DIR__ . '/../config.php';

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'inline_register') {
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
                throw new RuntimeException('Email is already in use.');
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
            flash_success('Account created. Continue checkout.');
            redirect('/user/checkout.php');
        }

        if ($action === 'inline_login') {
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
                throw new RuntimeException('Invalid credentials.');
            }
            if ($user['status'] !== 'ACTIVE') {
                throw new RuntimeException('Account is disabled.');
            }

            $_SESSION['user_id'] = (int) $user['id'];
            session_regenerate_id(true);
            mark_auth_session_started();
            rate_limit_reset('user_login', $loginRateKey);
            merge_guest_state_into_user((int) $_SESSION['user_id']);
            flash_success('Login successful.');
            redirect('/user/checkout.php');
        }

        if ($action === 'apply_coupon') {
            require_user();
            $userId = (int) $_SESSION['user_id'];
            $couponCode = strtoupper(trim((string) ($_POST['coupon_code'] ?? '')));
            if ($couponCode === '') {
                throw new RuntimeException('Please enter a coupon code.');
            }

            $actor = actor_context();
            $summary = cart_summary($actor['user_id'], $actor['session_id']);
            $coupon = find_coupon($couponCode, $userId);
            if (($coupon['type'] ?? '') === 'PRIVATE_LATE' && (float) $summary['subtotal'] < LATE_COUPON_MIN_ORDER_AMOUNT) {
                throw new RuntimeException('This coupon is valid only for orders above NPR ' . money(LATE_COUPON_MIN_ORDER_AMOUNT) . '.');
            }

            flash_success('Coupon applied.');
            redirect('/user/checkout.php?coupon=' . urlencode($couponCode));
        }

        if ($action === 'place_order') {
            require_user();
            $userId = (int) $_SESSION['user_id'];
            $addressId = (int) ($_POST['address_id'] ?? 0);
            $couponCode = trim((string) ($_POST['coupon_code'] ?? ''));
            $gateway = strtoupper(trim((string) ($_POST['gateway'] ?? '')));

            $savedAddresses = get_user_addresses($userId);
            if (!$savedAddresses) {
                throw new RuntimeException('No saved address found. Please add an address from the Addresses page.');
            }

            if ($addressId <= 0) {
                throw new RuntimeException('Please select a saved address.');
            }

            if (!in_array($gateway, ['STRIPE', 'ESEWA', 'KHALTI'], true)) {
                throw new RuntimeException('Please select a payment gateway.');
            }

            $order = create_order_from_cart($userId, $addressId, $couponCode);
            flash_success('Order created. Proceed to payment.');
            redirect('/user/payment.php?order=' . (int) $order['id'] . '&gateway=' . urlencode($gateway));
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
        redirect('/user/checkout.php');
    }
}

$title = 'Checkout';
require __DIR__ . '/header.php';
$actor = actor_context();
$summary = cart_summary($actor['user_id'], $actor['session_id']);
$user = current_user();
$addresses = $user ? get_user_addresses((int) $user['id']) : [];
$defaultAddressId = (int) ($addresses[0]['id'] ?? 0);
$appliedCouponCode = '';
$appliedCoupon = null;
$discountPreview = ['discount_percent' => 0.0, 'discount_amount' => 0.0];
$payableTotal = (float) $summary['subtotal'];
if ($user) {
    $appliedCouponCode = strtoupper(trim((string) ($_GET['coupon'] ?? '')));
    if ($appliedCouponCode !== '') {
        try {
            $appliedCoupon = find_coupon($appliedCouponCode, (int) $user['id']);
            if (($appliedCoupon['type'] ?? '') === 'PRIVATE_LATE' && (float) $summary['subtotal'] < LATE_COUPON_MIN_ORDER_AMOUNT) {
                throw new RuntimeException('This coupon is valid only for orders above NPR ' . money(LATE_COUPON_MIN_ORDER_AMOUNT) . '.');
            }
            $discountPreview = calculate_discount((float) $summary['subtotal'], $appliedCoupon);
        } catch (Throwable $e) {
            flash_error($e->getMessage());
            redirect('/user/checkout.php');
        }
    }
}
$payableTotal = max(0.0, round((float) $summary['subtotal'] - (float) $discountPreview['discount_amount'], 2));
?>
<h1 class="h4 mb-3">Checkout</h1>
<?php if (!$user): ?>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card"><div class="card-body">
                <h5>Inline Login</h5>
                <form method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="inline_login">
                    <div class="col-12"><input class="form-control" name="email" type="email" placeholder="Email" required></div>
                    <div class="col-12"><input class="form-control" name="password" type="password" placeholder="Password" required></div>
                    <div class="col-12"><button class="btn btn-primary w-100">Login to Continue</button></div>
                </form>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card"><div class="card-body">
                <h5>Inline Registration</h5>
                <form method="post" class="row g-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="inline_register">
                    <div class="col-12"><input class="form-control" name="full_name" placeholder="Full name" required></div>
                    <div class="col-12"><input class="form-control" name="email" type="email" placeholder="Email" required></div>
                    <div class="col-12"><input class="form-control" name="phone" placeholder="Phone" required></div>
                    <div class="col-12"><input class="form-control" name="password" type="password" placeholder="Password" required></div>
                    <div class="col-12"><button class="btn btn-success w-100">Register and Continue</button></div>
                </form>
            </div></div>
        </div>
    </div>
<?php else: ?>
    <form method="post">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body row g-3">
                        <div class="col-12">
                            <label class="form-label">Select Address</label>
                            <?php if (!$addresses): ?>
                                <div class="alert alert-warning mb-0">
                                    No saved address found. Please add an address from
                                    <a href="<?= e(base_url('/user/addresses.php')) ?>" class="alert-link">Addresses</a>
                                    before checkout.
                                </div>
                            <?php else: ?>
                                <select name="address_id" class="form-select" required>
                                    <option value="">-- Select saved address --</option>
                                    <?php foreach ($addresses as $addr): ?>
                                        <?php
                                        $line1 = trim((string) ($addr['address_line_1'] ?? ''));
                                        $line2 = trim((string) ($addr['address_line_2'] ?? ''));
                                        $city = trim((string) ($addr['city'] ?? ''));
                                        $zone = trim((string) ($addr['zone'] ?? ''));
                                        $hasTextAddress = ($line1 !== '' || $line2 !== '' || $city !== '' || $zone !== '');
                                        ?>
                                        <option value="<?= (int) $addr['id'] ?>" <?= (int) $addr['id'] === $defaultAddressId ? 'selected' : '' ?>>
                                            <?= e((string) $addr['address_name']) ?>:
                                            <?php if ($hasTextAddress): ?>
                                                <?= e($line1) ?>
                                                <?php if ($line2 !== ''): ?>
                                                    , <?= e($line2) ?>
                                                <?php endif; ?>
                                                <?php if ($zone !== ''): ?>
                                                    , <?= e($zone) ?>
                                                <?php endif; ?>
                                                <?php if ($city !== ''): ?>
                                                    , <?= e($city) ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                Pinned location (map)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted d-block mt-2">
                                    Need a new address?
                                    <a href="<?= e(base_url('/user/addresses.php')) ?>">Add it from your Addresses page</a>.
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-body">
                        <h6>Order Summary</h6>
                        <p class="mb-1">Items: <?= (int) $summary['count'] ?></p>
                        <p class="mb-1">Subtotal: NPR <?= e(money((float) $summary['subtotal'])) ?></p>
                        <p class="mb-1">Discount: -NPR <?= e(money((float) $discountPreview['discount_amount'])) ?>
                            <?php if ((float) $discountPreview['discount_percent'] > 0): ?>
                                <span class="text-success">(<?= e((string) $discountPreview['discount_percent']) ?>% off)</span>
                            <?php endif; ?>
                        </p>
                        <hr class="my-2">
                        <p class="mb-0 fw-semibold">Payable: NPR <?= e(money($payableTotal)) ?></p>
                        <?php if ($appliedCoupon): ?>
                            <small class="text-success d-block mt-2">Applied coupon: <?= e((string) $appliedCoupon['code']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body row g-3">
                        <div class="col-12">
                            <label class="form-label">Coupon Code</label>
                            <div class="input-group">
                                <input class="form-control" name="coupon_code" value="<?= e($appliedCouponCode) ?>" placeholder="Enter coupon code">
                                <button type="submit" name="action" value="apply_coupon" class="btn btn-outline-primary" formnovalidate>Apply Coupon</button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="gateway" required>
                                <option value="">Select payment gateway</option>
                                <option value="STRIPE">Stripe</option>
                                <option value="ESEWA">eSewa</option>
                                <option value="KHALTI">Khalti</option>
                            </select>
                        </div>
                        <div class="col-12 d-grid">
                            <button type="submit" name="action" value="place_order" class="btn btn-primary" <?= !$addresses ? 'disabled' : '' ?>>Place Order and Pay</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
<?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
