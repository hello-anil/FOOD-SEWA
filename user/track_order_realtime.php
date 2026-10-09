<?php
require_once __DIR__ . '/../config.php';
require_user();

$userId = (int) $_SESSION['user_id'];

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($isPost) {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $orderCode = trim((string) ($_POST['order_code'] ?? ''));
    try {
        $orderCode = validate_order_code($orderCode);
    } catch (Throwable $e) {
        http_json(['success' => false, 'error' => $e->getMessage()], 422);
    }

    if ($action !== 'claim_coupon' || $orderCode === '') {
        http_json(['success' => false, 'error' => 'Invalid claim request.'], 422);
    }

    $order = user_order_by_code($userId, $orderCode);
    if (!$order) {
        http_json(['success' => false, 'error' => 'Order not found.'], 404);
    }

    try {
        $coupon = claim_late_coupon_for_order((int) $order['id'], $userId);
        if (!$coupon) {
            http_json(['success' => false, 'error' => 'Coupon is not claimable yet.'], 422);
        }

        http_json([
            'success' => true,
            'coupon' => [
                'code' => $coupon['code'],
                'discount_percent' => (float) $coupon['discount_percent'],
                'min_order_amount' => (float) LATE_COUPON_MIN_ORDER_AMOUNT,
                'expires_at' => $coupon['expires_at'] ?? null,
            ],
        ]);
    } catch (Throwable $e) {
        http_json(['success' => false, 'error' => $e->getMessage()], 422);
    }
}

$orderCodeInput = trim((string) ($_GET['order_code'] ?? ''));
if ($orderCodeInput === '') {
    http_json(['error' => 'order_code is required.'], 422);
}

try {
    $orderCode = validate_order_code($orderCodeInput);
} catch (Throwable $e) {
    http_json(['error' => $e->getMessage()], 422);
}

$order = user_order_by_code($userId, $orderCode);
if (!$order) {
    http_json(['error' => 'Order not found'], 404);
}

$payload = tracking_payload($order);
$payload['order_code'] = $orderCode;
$payload['delivery_status'] = $order['delivery_status'];
$payload['tracking_number'] = $order['tracking_number'];
$payload['late_coupon'] = null;

http_json($payload);
