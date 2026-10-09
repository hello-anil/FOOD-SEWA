<?php
require_once __DIR__ . '/../config.php';

$actor = actor_context();

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update') {
            update_cart_item_qty((int) $_POST['cart_item_id'], (int) $_POST['qty'], $actor['user_id'], $actor['session_id']);
            flash_success('Cart updated.');
        }

        if ($action === 'remove') {
            remove_cart_item((int) $_POST['cart_item_id'], $actor['user_id'], $actor['session_id']);
            flash_success('Item removed.');
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }

    redirect('/user/cart.php');
}

$summary = cart_summary($actor['user_id'], $actor['session_id']);
$title = 'Cart';
require __DIR__ . '/header.php';
?>
<div class="row">
    <div class="col-md-8">
        <h1 class="h4 mb-3">Your Cart</h1>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Total</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($summary['items'] as $item): ?>
                    <tr>
                        <td><?= e($item['name']) ?></td>
                        <td>NPR <?= e(money((float) $item['price_snapshot'])) ?></td>
                        <td>
                            <form method="post" class="d-flex gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="cart_item_id" value="<?= (int) $item['id'] ?>">
                                <input type="number" name="qty" min="0" class="form-control form-control-sm" value="<?= (int) $item['qty'] ?>">
                                <button class="btn btn-sm btn-outline-primary">Update</button>
                            </form>
                        </td>
                        <td>NPR <?= e(money((float) $item['price_snapshot'] * (int) $item['qty'])) ?></td>
                        <td>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_item_id" value="<?= (int) $item['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between">
            <a href="<?= e(base_url('/user/foods.php')) ?>" class="btn btn-outline-secondary">Continue Shopping</a>
            <a href="<?= e(base_url('/user/checkout.php')) ?>" class="btn btn-primary">Proceed to Checkout</a>
        </div>
    </div>
    <div class="col-md-4"><?php require __DIR__ . '/cart_sidebar.php'; ?></div>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
