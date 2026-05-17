<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
$cfg  = app_config();
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? $cfg['app']['name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
    <a href="index.php" class="brand"><?= e($cfg['app']['name']) ?></a>
    <?php if ($user): ?>
        <nav>
            <a href="tasks.php">Tasks</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="admin.php">Users</a>
            <?php endif; ?>
            <span class="who">Hi, <?= e($user['name']) ?></span>
            <a href="logout.php" class="logout">Log out</a>
        </nav>
    <?php endif; ?>
</header>
<main class="container">
<?php foreach (flash_get() as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
