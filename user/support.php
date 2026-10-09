<?php
require_once __DIR__ . '/../config.php';
require_user();
$userId = (int) $_SESSION['user_id'];

if (is_post()) {
    verify_csrf();
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    try {
        create_support_ticket($userId, $subject, $message);
        flash_success('Support request submitted. We will contact you soon.');
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }

    redirect('/user/support.php');
}

$title = 'Support';
require __DIR__ . '/header.php';
?>
<div class="row">
    <div class="col-md-8">
        <div class="card"><div class="card-body">
            <h1 class="h5">Support</h1>
            <p class="text-muted">Need help with an order, payment, or account issue?</p>
            <form method="post" class="row g-2">
                <?= csrf_field() ?>
                <div class="col-12"><input class="form-control" name="subject" placeholder="Subject" required></div>
                <div class="col-12"><textarea class="form-control" name="message" rows="5" placeholder="Describe your issue" required></textarea></div>
                <div class="col-12"><button class="btn btn-primary">Submit Ticket</button></div>
            </form>
        </div></div>
    </div>
    <div class="col-md-4"><?php require __DIR__ . '/sidebar.php'; ?></div>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
