<?php
require_once __DIR__ . '/../config.php';
require_once APP_ROOT . '/components/payment/stripe.php';
require_once APP_ROOT . '/components/payment/esewa.php';
require_once APP_ROOT . '/components/payment/khalti.php';

require_user();

$orderId = (int) ($_GET['order'] ?? 0);
$gateway = strtoupper((string) ($_GET['gateway'] ?? ''));
if ($orderId <= 0 || !in_array($gateway, ['STRIPE', 'ESEWA', 'KHALTI'], true)) {
    flash_error('Invalid payment request.');
    redirect('/user/order.php');
}

assert_order_owner($orderId, (int) $_SESSION['user_id']);
$order = get_order_by_id($orderId);
if (!$order) {
    flash_error('Order not found.');
    redirect('/user/order.php');
}

if ($order['payment_status'] === 'PAID') {
    flash_success('Order is already paid.');
    redirect('/user/order.php?order_code=' . urlencode((string) $order['order_code']));
}

$items = order_items($orderId);

try {
    if ($gateway === 'STRIPE') {
        $session = stripe_create_checkout_session($order, $items, current_user());
        $url = (string) ($session['url'] ?? '');
        $sessionId = (string) ($session['id'] ?? '');
        if ($url === '') {
            throw new RuntimeException('Stripe did not return checkout URL.');
        }
        if ($sessionId !== '') {
            $_SESSION['stripe_checkout_sessions'] = $_SESSION['stripe_checkout_sessions'] ?? [];
            $_SESSION['stripe_checkout_sessions'][(string) $orderId] = $sessionId;
        }

        redirect($url);
    }

    if ($gateway === 'ESEWA') {
        $payload = esewa_build_checkout_payload($order);
        $_SESSION['esewa_transactions'] = $_SESSION['esewa_transactions'] ?? [];
        $_SESSION['esewa_transactions'][(string) $orderId] = (string) ($payload['fields']['transaction_uuid'] ?? '');
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Redirecting to eSewa...</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="<?= e(base_url('/assets/css/user-theme.css')) ?>" rel="stylesheet">
        </head>
        <body class="user-app">
        <main class="container py-5 user-main">
            <div class="card mx-auto" style="max-width: 480px;">
                <div class="card-body text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
                    <h1 class="h5 mb-2">Redirecting to eSewa</h1>
                    <p class="text-muted mb-0">Please wait while we connect you to the payment gateway.</p>
                </div>
            </div>
        </main>
        <form id="esewaForm" action="<?= e($payload['form_url']) ?>" method="POST">
            <?php foreach ($payload['fields'] as $key => $value): ?>
                <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
            <?php endforeach; ?>
        </form>
        <script>document.getElementById('esewaForm').submit();</script>
        </body>
        </html>
        <?php
        exit;
    }

    if ($gateway === 'KHALTI') {
        $user = current_user();
        $response = khalti_initiate_payment($order, $user ?: []);
        $payUrl = (string) ($response['payment_url'] ?? '');
        $pidx = (string) ($response['pidx'] ?? '');
        if ($payUrl === '') {
            throw new RuntimeException('Khalti did not return payment URL.');
        }
        if ($pidx !== '') {
            $_SESSION['khalti_pidx'] = $_SESSION['khalti_pidx'] ?? [];
            $_SESSION['khalti_pidx'][(string) $orderId] = $pidx;
        }

        redirect($payUrl);
    }
} catch (Throwable $e) {
    flash_error($e->getMessage());
    redirect('/user/order.php?order_code=' . urlencode((string) $order['order_code']));
}
