<?php
$currentPath = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$isActive = static function (array $paths) use ($currentPath): string {
    return in_array($currentPath, $paths, true) ? 'active' : '';
};
?>
<aside class="col-12 col-lg-2 admin-sidebar p-3">
    <h5 class="mb-3">Admin Panel</h5>
    <ul class="nav nav-pills flex-column gap-2">
        <li class="nav-item"><a class="nav-link <?= e($isActive(['dashboard.php'])) ?>" href="<?= e(base_url('/admin/dashboard.php')) ?>">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?= e($isActive(['categories.php'])) ?>" href="<?= e(base_url('/admin/categories.php')) ?>">Categories</a></li>
        <li class="nav-item"><a class="nav-link <?= e($isActive(['foods.php'])) ?>" href="<?= e(base_url('/admin/foods.php')) ?>">Foods</a></li>
        <li class="nav-item"><a class="nav-link <?= e($isActive(['orders.php'])) ?>" href="<?= e(base_url('/admin/orders.php')) ?>">Orders</a></li>
        <li class="nav-item"><a class="nav-link <?= e($isActive(['delivery_management.php'])) ?>" href="<?= e(base_url('/admin/delivery_management.php')) ?>">Delivery</a></li>
        <li class="nav-item"><a class="nav-link <?= e($isActive(['users.php'])) ?>" href="<?= e(base_url('/admin/users.php')) ?>">Users</a></li>
        <li class="nav-item"><a class="nav-link <?= e($isActive(['payment.php'])) ?>" href="<?= e(base_url('/admin/payment.php')) ?>">Payments</a></li>
        <li class="nav-item"><a class="nav-link <?= e($isActive(['coupons.php'])) ?>" href="<?= e(base_url('/admin/coupons.php')) ?>">Coupons</a></li>
        <li class="nav-item"><a class="nav-link <?= e($isActive(['settings.php'])) ?>" href="<?= e(base_url('/admin/settings.php')) ?>">Settings</a></li>
        <li class="nav-item">
            <form method="post" action="<?= e(base_url('/admin/logout.php')) ?>" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="nav-link nav-logout border-0 bg-transparent text-start w-100">Logout</button>
            </form>
        </li>
    </ul>
</aside>
