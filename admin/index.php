<?php
require_once __DIR__ . '/../config.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('/admin/dashboard.php');
}

redirect('/admin/auth.php');
