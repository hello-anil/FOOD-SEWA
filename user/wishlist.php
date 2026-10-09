<?php
require_once __DIR__ . '/../config.php';

$actor = actor_context();
if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $foodId = (int) ($_POST['food_id'] ?? 0);

    try {
        if ($action === 'toggle') {
            toggle_wishlist_item($foodId, $actor['user_id'], $actor['session_id']);
            flash_success('Wishlist updated.');
        }

        if ($action === 'add_to_cart') {
            add_to_cart($foodId, 1, $actor['user_id'], $actor['session_id']);
            flash_success('Added to cart.');
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }

    redirect('/user/wishlist.php');
}

$items = wishlist_items($actor['user_id'], $actor['session_id']);
$title = 'Wishlist';
require __DIR__ . '/header.php';
?>
<h1 class="h4 mb-3">Wishlist</h1>
<div class="row g-3">
    <?php foreach ($items as $item): ?>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5><?= e($item['name']) ?></h5>
                    <p>NPR <?= e(money((float) $item['price'])) ?></p>
                    <div class="d-flex gap-2">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_to_cart">
                            <input type="hidden" name="food_id" value="<?= (int) $item['food_id'] ?>">
                            <button class="btn btn-sm btn-primary">Add to Cart</button>
                        </form>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="food_id" value="<?= (int) $item['food_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">Remove</button>
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
