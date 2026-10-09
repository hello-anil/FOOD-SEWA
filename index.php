<?php
require_once __DIR__ . '/config.php';

$title = 'Home';
$categoryId = isset($_GET['category']) ? (int) $_GET['category'] : null;
$categories = active_categories();
$foods = active_foods($categoryId ?: null);

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $actor = actor_context();

    try {
        if ($action === 'add_cart') {
            add_to_cart((int) $_POST['food_id'], (int) ($_POST['qty'] ?? 1), $actor['user_id'], $actor['session_id']);
            flash_success('Item added to cart.');
        }

        if ($action === 'toggle_wishlist') {
            $added = toggle_wishlist_item((int) $_POST['food_id'], $actor['user_id'], $actor['session_id']);
            flash_success($added ? 'Added to wishlist.' : 'Removed from wishlist.');
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }

    redirect('/index.php');
}

require __DIR__ . '/user/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Order Your Favorite Food</h1>
    <a href="<?= e(base_url('/user/checkout.php')) ?>" class="btn btn-primary">Checkout</a>
</div>

<form method="get" class="row g-2 mb-4">
    <div class="col-md-4">
        <select name="category" class="form-select">
            <option value="">All categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-secondary w-100">Filter</button>
    </div>
</form>

<div class="row g-3">
    <?php foreach ($foods as $food): ?>
        <div class="col-md-4">
            <div class="card h-100">
                <?php if (!empty($food['image_path'])): ?>
                    <img src="<?= e(base_url('/uploads/foods/' . $food['image_path'])) ?>" class="card-img-top" style="height:180px;object-fit:cover;" alt="food">
                <?php endif; ?>
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title"><?= e($food['name']) ?></h5>
                    <p class="small text-muted mb-1">Category: <?= e($food['category_name']) ?></p>
                    <p class="small"><?= e((string) $food['description']) ?></p>
                    <p class="fw-semibold mb-1">NPR <?= e(money((float) $food['price'])) ?></p>
                    <p class="text-muted small mb-3">Stock: <?= (int) $food['stock_qty'] ?></p>
                    <div class="mt-auto d-flex gap-2">
                        <form method="post" class="d-flex gap-2 flex-grow-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_cart">
                            <input type="hidden" name="food_id" value="<?= (int) $food['id'] ?>">
                            <input type="number" class="form-control form-control-sm" min="1" value="1" name="qty">
                            <button class="btn btn-sm btn-primary">Add</button>
                        </form>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_wishlist">
                            <input type="hidden" name="food_id" value="<?= (int) $food['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Wishlist</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
