<?php
require_once __DIR__ . '/../config.php';
$title = $title ?? 'FOOD SEWA';
$user = current_user();
$actor = actor_context();
$summary = cart_summary($actor['user_id'], $actor['session_id']);
$currentPath = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$isActive = static function (array $paths) use ($currentPath): string {
    return in_array($currentPath, $paths, true) ? 'active' : '';
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - FOOD SEWA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(base_url('/assets/css/user-theme.css')) ?>" rel="stylesheet">
</head>
<body class="user-app">
<nav class="navbar navbar-expand-lg user-navbar">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(base_url('/index.php')) ?>">FOOD SEWA</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="topNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link <?= e($isActive(['index.php', 'foods.php'])) ?>" href="<?= e(base_url('/user/foods.php')) ?>">Foods</a></li>
                <li class="nav-item"><a class="nav-link <?= e($isActive(['wishlist.php'])) ?>" href="<?= e(base_url('/user/wishlist.php')) ?>">Wishlist</a></li>
                <li class="nav-item"><a class="nav-link <?= e($isActive(['track.php'])) ?>" href="<?= e(base_url('/track.php')) ?>">Track</a></li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <?php if ($user): ?>
                    <li class="nav-item"><a class="nav-link <?= e($isActive(['dashboard.php', 'order.php', 'coupons.php', 'addresses.php', 'profile.php', 'support.php'])) ?>" href="<?= e(base_url('/user/dashboard.php')) ?>">My Account</a></li>
                    <li class="nav-item">
                        <form method="post" action="<?= e(base_url('/user/logout.php')) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="nav-link nav-logout border-0 bg-transparent p-0">Logout</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link <?= e($isActive(['auth.php'])) ?>" href="<?= e(base_url('/user/auth.php')) ?>">Login/Register</a></li>
                <?php endif; ?>
                <li class="nav-item">
                    <button
                        type="button"
                        class="nav-link fm-cart-icon-btn"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#fmCartDrawer"
                        aria-controls="fmCartDrawer"
                        aria-label="Open cart"
                    >
                        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M7 4a1 1 0 0 1 .98.804L8.18 6H20a1 1 0 0 1 .97 1.243l-1.5 6A1 1 0 0 1 18.5 14H9a1 1 0 0 1-.98-.804L6.22 5H4a1 1 0 0 1 0-2h3zm2.78 8h7.94l1-4H8.78l1 4zM10 20a2 2 0 1 1 .001-3.999A2 2 0 0 1 10 20zm7 0a2 2 0 1 1 .001-3.999A2 2 0 0 1 17 20z"/>
                        </svg>
                        <span class="fm-count"><?= (int) $summary['count'] ?></span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="offcanvas offcanvas-end fm-cart-drawer" tabindex="-1" id="fmCartDrawer" aria-labelledby="fmCartDrawerLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="fmCartDrawerLabel">Your Cart</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <?php if (!$summary['items']): ?>
            <div class="text-center py-4">
                <p class="mb-1">Your cart is empty.</p>
                <small class="text-muted">Add items to see them here.</small>
            </div>
        <?php else: ?>
            <div class="fm-cart-drawer-items flex-grow-1">
                <?php foreach ($summary['items'] as $item): ?>
                    <div class="fm-cart-drawer-item">
                        <div class="d-flex justify-content-between gap-2">
                            <div>
                                <p class="mb-1 fw-semibold"><?= e((string) $item['name']) ?></p>
                                <small class="text-muted">Qty <?= (int) $item['qty'] ?> x NPR <?= e(money((float) $item['price_snapshot'])) ?></small>
                            </div>
                            <p class="mb-0 fw-semibold">NPR <?= e(money((float) $item['price_snapshot'] * (int) $item['qty'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="border-top pt-3 mt-3">
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Subtotal</span>
                    <strong>NPR <?= e(money((float) $summary['subtotal'])) ?></strong>
                </div>
                <div class="d-grid gap-2">
                    <a class="btn btn-outline-primary" href="<?= e(base_url('/user/cart.php')) ?>">View Full Cart</a>
                    <a class="btn btn-primary" href="<?= e(base_url('/user/checkout.php')) ?>">Checkout</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<main class="container py-4 user-main">
    <?php render_flash(); ?>
