<?php
require_once __DIR__ . '/../config.php';

if (!is_post()) {
    redirect('/index.php');
}
verify_csrf();

unset($_SESSION['user_id']);
unset($_SESSION['_auth_started_at'], $_SESSION['_auth_last_seen']);
session_regenerate_id(true);
flash_success('Logged out successfully.');
redirect('/index.php');
