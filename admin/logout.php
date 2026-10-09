<?php
require_once __DIR__ . '/../config.php';

if (!is_post()) {
    redirect('/admin/dashboard.php');
}
verify_csrf();

unset($_SESSION['admin_id']);
unset($_SESSION['_auth_started_at'], $_SESSION['_auth_last_seen']);
session_regenerate_id(true);
flash_success('Admin logged out.');
redirect('/admin/auth.php');
