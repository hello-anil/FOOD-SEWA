<?php
require_once __DIR__ . '/../config.php';

$actor = actor_context();
$summary = cart_summary($actor['user_id'], $actor['session_id']);
?>
<div class="card">
    <div class="card-body">
        <h6 class="mb-3">Cart Summary</h6>
        <div class="d-flex justify-content-between mb-2">
            <span class="text-muted">Items</span>
            <strong><?= (int) $summary['count'] ?></strong>
        </div>
        <div class="d-flex justify-content-between">
            <span class="text-muted">Subtotal</span>
            <strong>NPR <?= e(money((float) $summary['subtotal'])) ?></strong>
        </div>
    </div>
</div>
