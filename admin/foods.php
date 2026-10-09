<?php
require_once __DIR__ . '/../config.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $categoryId = (int) $_POST['category_id'];
            $name = trim((string) ($_POST['name'] ?? ''));
            $desc = trim((string) ($_POST['description'] ?? ''));
            $price = (float) ($_POST['price'] ?? 0);
            $stock = (int) ($_POST['stock_qty'] ?? 0);
            $status = validate_enum(strtoupper((string) ($_POST['status'] ?? 'ACTIVE')), ['ACTIVE', 'DISABLED'], 'Invalid food status.');

            if (str_length($name) < 2 || str_length($name) > 140 || !preg_match('/^[\p{L}\p{N} .,&()\'+-]+$/u', $name)) {
                throw new RuntimeException('Food name must be 2-140 characters and contain valid characters.');
            }
            if ($price < 0 || $price > 1000000) {
                throw new RuntimeException('Price must be between 0 and 1000000.');
            }
            if ($stock < 0 || $stock > 100000) {
                throw new RuntimeException('Stock must be between 0 and 100000.');
            }
            if (strlen($desc) > 5000) {
                throw new RuntimeException('Description is too long.');
            }

            $catStmt = db()->prepare('SELECT id, status FROM categories WHERE id = ? LIMIT 1');
            $catStmt->bind_param('i', $categoryId);
            $catStmt->execute();
            $category = $catStmt->get_result()->fetch_assoc();
            if (!$category || (string) $category['status'] !== 'ACTIVE') {
                throw new RuntimeException('Please select an active category.');
            }

            $image = null;
            if (!empty($_FILES['image']['name'])) {
                $image = upload_image($_FILES['image'], APP_ROOT . '/uploads/foods');
            }

            $stmt = db()->prepare('INSERT INTO foods (category_id, name, description, price, stock_qty, image_path, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->bind_param('issdiss', $categoryId, $name, $desc, $price, $stock, $image, $status);
            $stmt->execute();
            flash_success('Food created.');
        }

        if ($action === 'update') {
            $id = (int) $_POST['id'];
            $categoryId = (int) $_POST['category_id'];
            $name = trim((string) ($_POST['name'] ?? ''));
            $desc = trim((string) ($_POST['description'] ?? ''));
            $price = (float) ($_POST['price'] ?? 0);
            $stock = (int) ($_POST['stock_qty'] ?? 0);
            $status = validate_enum(strtoupper((string) ($_POST['status'] ?? 'ACTIVE')), ['ACTIVE', 'DISABLED'], 'Invalid food status.');

            if (str_length($name) < 2 || str_length($name) > 140 || !preg_match('/^[\p{L}\p{N} .,&()\'+-]+$/u', $name)) {
                throw new RuntimeException('Food name must be 2-140 characters and contain valid characters.');
            }
            if ($id <= 0) {
                throw new RuntimeException('Invalid food id.');
            }
            if ($price < 0 || $price > 1000000) {
                throw new RuntimeException('Price must be between 0 and 1000000.');
            }
            if ($stock < 0 || $stock > 100000) {
                throw new RuntimeException('Stock must be between 0 and 100000.');
            }
            if (strlen($desc) > 5000) {
                throw new RuntimeException('Description is too long.');
            }

            $catStmt = db()->prepare('SELECT id, status FROM categories WHERE id = ? LIMIT 1');
            $catStmt->bind_param('i', $categoryId);
            $catStmt->execute();
            $category = $catStmt->get_result()->fetch_assoc();
            if (!$category || (string) $category['status'] !== 'ACTIVE') {
                throw new RuntimeException('Please select an active category.');
            }

            $imagePath = null;
            if (!empty($_FILES['image']['name'])) {
                $imagePath = upload_image($_FILES['image'], APP_ROOT . '/uploads/foods');
            }

            if ($imagePath) {
                $stmt = db()->prepare('UPDATE foods SET category_id = ?, name = ?, description = ?, price = ?, stock_qty = ?, image_path = ?, status = ? WHERE id = ?');
                $stmt->bind_param('issdissi', $categoryId, $name, $desc, $price, $stock, $imagePath, $status, $id);
            } else {
                $stmt = db()->prepare('UPDATE foods SET category_id = ?, name = ?, description = ?, price = ?, stock_qty = ?, status = ? WHERE id = ?');
                $stmt->bind_param('issdisi', $categoryId, $name, $desc, $price, $stock, $status, $id);
            }
            $stmt->execute();
            flash_success('Food updated.');
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid food id.');
            }

            $stmt = db()->prepare('SELECT id, image_path FROM foods WHERE id = ? LIMIT 1');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $food = $stmt->get_result()->fetch_assoc();
            if (!$food) {
                throw new RuntimeException('Food not found or already deleted.');
            }

            $tablesToCheck = [
                'cart_items' => 'cart',
                'order_items' => 'order history',
                'wishlist_items' => 'wishlist',
            ];

            foreach ($tablesToCheck as $table => $label) {
                $check = db()->prepare("SELECT COUNT(*) AS total FROM {$table} WHERE food_id = ?");
                $check->bind_param('i', $id);
                $check->execute();
                $row = $check->get_result()->fetch_assoc();
                if ((int) ($row['total'] ?? 0) > 0) {
                    throw new RuntimeException('This food cannot be deleted because it is used in ' . $label . '.');
                }
            }

            $delete = db()->prepare('DELETE FROM foods WHERE id = ?');
            $delete->bind_param('i', $id);
            $delete->execute();

            if ($delete->affected_rows < 1) {
                throw new RuntimeException('Food not found or already deleted.');
            }

            $imagePath = trim((string) ($food['image_path'] ?? ''));
            if ($imagePath !== '') {
                $fullPath = APP_ROOT . '/uploads/foods/' . basename($imagePath);
                if (is_file($fullPath)) {
                    @unlink($fullPath);
                }
            }

            flash_success('Food deleted.');
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }

    redirect('/admin/foods.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$editFood = null;
if ($editId > 0) {
    $stmt = db()->prepare('SELECT * FROM foods WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editFood = $stmt->get_result()->fetch_assoc() ?: null;
}

$catsStmt = db()->prepare('SELECT id, name FROM categories ORDER BY name ASC');
$catsStmt->execute();
$categories = $catsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$foodsStmt = db()->prepare('SELECT f.*, c.name AS category_name FROM foods f INNER JOIN categories c ON c.id = f.category_id ORDER BY f.id DESC');
$foodsStmt->execute();
$foods = $foodsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$title = 'Foods';
require __DIR__ . '/header.php';
?>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <h6><?= $editFood ? 'Edit Food' : 'Add Food' ?></h6>
            <form method="post" enctype="multipart/form-data" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editFood ? 'update' : 'create' ?>">
                <?php if ($editFood): ?><input type="hidden" name="id" value="<?= (int) $editFood['id'] ?>"><?php endif; ?>
                <div class="col-12"><select name="category_id" class="form-select" required><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= $editFood && (int) $editFood['category_id'] === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><input name="name" class="form-control" placeholder="Food name" value="<?= e((string) ($editFood['name'] ?? '')) ?>" required></div>
                <div class="col-12"><textarea name="description" class="form-control" placeholder="Description"><?= e((string) ($editFood['description'] ?? '')) ?></textarea></div>
                <div class="col-6"><input name="price" type="number" step="0.01" class="form-control" placeholder="Price" value="<?= e((string) ($editFood['price'] ?? '')) ?>" required></div>
                <div class="col-6"><input name="stock_qty" type="number" class="form-control" placeholder="Stock" value="<?= e((string) ($editFood['stock_qty'] ?? '')) ?>" required></div>
                <div class="col-12"><input name="image" type="file" class="form-control" accept="image/*"></div>
                <div class="col-12"><select name="status" class="form-select"><option value="ACTIVE" <?= $editFood && $editFood['status'] === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option><option value="DISABLED" <?= $editFood && $editFood['status'] === 'DISABLED' ? 'selected' : '' ?>>DISABLED</option></select></div>
                <div class="col-12"><button class="btn btn-primary w-100"><?= $editFood ? 'Update' : 'Create' ?></button></div>
                <?php if ($editFood): ?><div class="col-12"><a href="<?= e(base_url('/admin/foods.php')) ?>" class="btn btn-outline-secondary w-100">Cancel Edit</a></div><?php endif; ?>
            </form>
        </div></div>
    </div>
    <div class="col-md-8">
        <div class="card"><div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($foods as $food): ?>
                            <tr>
                                <td><?= (int) $food['id'] ?></td>
                                <td><?= e($food['name']) ?></td>
                                <td><?= e($food['category_name']) ?></td>
                                <td><?= e(money((float) $food['price'])) ?></td>
                                <td><?= (int) $food['stock_qty'] ?></td>
                                <td><?= e($food['status']) ?></td>
                                <td>
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/admin/foods.php?edit=' . (int) $food['id'])) ?>">Edit</a>
                                        <form method="post" onsubmit="return confirm('Delete this food?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $food['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
