<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Raja';
$pageEyebrow = $pageEyebrow ?? 'Cockpit audition';
$user = current_user();
$flashes = consume_flashes();
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= h($pageTitle) ?> · Raja</title>
    <link rel="stylesheet" href="<?= h(url('/assets/css/style.css')) ?>">
    <script defer src="<?= h(url('/assets/js/app.js')) ?>"></script>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="app-main">
        <header class="topbar">
            <button class="icon-button menu-toggle" type="button" aria-label="Ouvrir le menu" data-menu-toggle>☰</button>
            <div>
                <p class="eyebrow"><?= h($pageEyebrow) ?></p>
                <h1><?= h($pageTitle) ?></h1>
            </div>
            <div class="topbar__user">
                <span class="avatar"><?= h(mb_strtoupper(mb_substr($user['name'] ?? 'R', 0, 1))) ?></span>
                <div><strong><?= h($user['name'] ?? '') ?></strong><small><?= h(ucfirst($user['role'] ?? '')) ?></small></div>
            </div>
        </header>
        <main class="content">
            <?php foreach ($flashes as $notice): ?>
                <div class="notice notice--<?= h($notice['type']) ?>" role="status">
                    <span><?= h($notice['message']) ?></span>
                    <button type="button" aria-label="Fermer" data-dismiss>×</button>
                </div>
            <?php endforeach; ?>
