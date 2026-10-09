<?php
$currentPath = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$isActive = static function (string $path) use ($currentPath): string {
    return $currentPath === $path ? 'active' : '';
};
?>
<div class="card mb-3 user-quick-links">
    <div class="card-body">
        <h6 class="card-title mb-3">Quick Links</h6>
        <div class="list-group list-group-flush">
            <a class="list-group-item list-group-item-action <?= e($isActive('dashboard.php')) ?>" href="<?= e(base_url('/user/dashboard.php')) ?>">Dashboard</a>
            <a class="list-group-item list-group-item-action <?= e($isActive('order.php')) ?>" href="<?= e(base_url('/user/order.php')) ?>">Orders</a>
            <a class="list-group-item list-group-item-action <?= e($isActive('coupons.php')) ?>" href="<?= e(base_url('/user/coupons.php')) ?>">My Coupons</a>
            <a class="list-group-item list-group-item-action <?= e($isActive('addresses.php')) ?>" href="<?= e(base_url('/user/addresses.php')) ?>">Addresses</a>
            <a class="list-group-item list-group-item-action <?= e($isActive('profile.php')) ?>" href="<?= e(base_url('/user/profile.php')) ?>">Profile</a>
            <a class="list-group-item list-group-item-action <?= e($isActive('support.php')) ?>" href="<?= e(base_url('/user/support.php')) ?>">Support</a>
        </div>
    </div>
</div>
