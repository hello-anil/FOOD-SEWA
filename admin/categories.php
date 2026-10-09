<?php
require_once __DIR__ . '/../config.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if (str_length($name) < 2 || str_length($name) > 120) {
                throw new RuntimeException('Category name must be 2-120 characters.');
            }
            $status = 'ACTIVE';
            $stmt = db()->prepare('INSERT INTO categories (name, status, created_at) VALUES (?, ?, NOW())');
            $stmt->bind_param('ss', $name, $status);
            $stmt->execute();
            flash_success('Category created.');
        }

        if ($action === 'update') {
            $id = (int) $_POST['id'];
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($id <= 0) {
                throw new RuntimeException('Invalid category id.');
            }
            if (str_length($name) < 2 || str_length($name) > 120) {
                throw new RuntimeException('Category name must be 2-120 characters.');
            }
            $status = validate_enum(strtoupper((string) ($_POST['status'] ?? 'ACTIVE')), ['ACTIVE', 'DISABLED'], 'Invalid category status.');
            $stmt = db()->prepare('UPDATE categories SET name = ?, status = ? WHERE id = ?');
            $stmt->bind_param('ssi', $name, $status, $id);
            $stmt->execute();
            flash_success('Category updated.');
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid category id.');
            }

            $stmt = db()->prepare('SELECT COUNT(*) AS total FROM foods WHERE category_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $usage = $stmt->get_result()->fetch_assoc();
            $foodCount = (int) ($usage['total'] ?? 0);

            if ($foodCount > 0) {
                throw new RuntimeException('This category cannot be deleted because foods are listed under it.');
            }

            $stmt = db()->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($stmt->affected_rows < 1) {
                throw new RuntimeException('Category not found or already deleted.');
            }

            flash_success('Category deleted.');
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }

    redirect('/admin/categories.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$editCategory = null;
if ($editId > 0) {
    $stmt = db()->prepare('SELECT id, name, status FROM categories WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editCategory = $stmt->get_result()->fetch_assoc() ?: null;
}

$stmt = db()->prepare('SELECT id, name, status, created_at FROM categories ORDER BY id DESC');
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$title = 'Categories';
require __DIR__ . '/header.php';
?>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <h6><?= $editCategory ? 'Edit Category' : 'Add Category' ?></h6>
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editCategory ? 'update' : 'create' ?>">
                <?php if ($editCategory): ?><input type="hidden" name="id" value="<?= (int) $editCategory['id'] ?>"><?php endif; ?>
                <div class="col-12"><input class="form-control" name="name" value="<?= e((string) ($editCategory['name'] ?? '')) ?>" placeholder="Category name" required></div>
                <div class="col-12">
                    <select class="form-select" name="status">
                        <option value="ACTIVE" <?= !$editCategory || $editCategory['status'] === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                        <option value="DISABLED" <?= $editCategory && $editCategory['status'] === 'DISABLED' ? 'selected' : '' ?>>DISABLED</option>
                    </select>
                </div>
                <div class="col-12"><button class="btn btn-primary w-100"><?= $editCategory ? 'Update' : 'Create' ?></button></div>
                <?php if ($editCategory): ?><div class="col-12"><a class="btn btn-outline-secondary w-100" href="<?= e(base_url('/admin/categories.php')) ?>">Cancel Edit</a></div><?php endif; ?>
            </form>
        </div></div>
    </div>
    <div class="col-md-8">
        <div class="card"><div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>ID</th><th>Name</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= (int) $cat['id'] ?></td>
                            <td><?= e($cat['name']) ?></td>
                            <td><?= e($cat['status']) ?></td>
                            <td>
                                <div class="d-flex gap-2 justify-content-end">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/admin/categories.php?edit=' . (int) $cat['id'])) ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Delete this category?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
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
