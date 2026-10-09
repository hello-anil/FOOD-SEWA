<?php
require_once __DIR__ . '/../config.php';
require_admin();
$title = $title ?? 'Admin';
$admin = current_admin();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - FOOD SEWA Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(base_url('/assets/css/admin-theme.css')) ?>" rel="stylesheet">
</head>
<body class="admin-app">
<div class="container-fluid admin-shell">
    <div class="row g-0">
        <?php require __DIR__ . '/sidebar.php'; ?>
        <main class="col-12 col-lg-10 ms-sm-auto px-4 py-4 admin-main">
            <div class="d-flex justify-content-between align-items-center mb-3 admin-topbar">
                <h1 class="h4 mb-0"><?= e($title) ?></h1>
                <small class="text-muted">Logged in: <?= e((string) ($admin['email'] ?? '')) ?></small>
            </div>
            <?php render_flash(); ?>
