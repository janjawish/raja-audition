<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    redirect('/pages/dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (attempt_login(post_string('email', '') ?? '', post_string('password', '') ?? '')) {
            $intended = $_SESSION['intended_url'] ?? url('/pages/dashboard.php');
            unset($_SESSION['intended_url']);
            header('Location: ' . $intended);
            exit;
        }
        $error = 'Adresse e-mail ou mot de passe incorrect.';
        usleep(300000);
    } catch (Throwable $exception) {
        $error = 'Raja ne peut pas se connecter à la base. Vérifiez la configuration et l’installation SQL.';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion · Raja</title><link rel="stylesheet" href="<?= h(url('/assets/css/style.css')) ?>">
</head>
<body class="login-page">
<section class="login-card">
    <div class="login-hero">
        <div><span class="brand__mark">R</span><h1>Raja</h1><p>Le cockpit simple de votre équipe audition pour suivre chaque dossier, chaque échéance et chaque facturation.</p></div>
        <small>Application interne · données hébergées sur votre réseau local</small>
    </div>
    <form class="login-form" method="post" autocomplete="on">
        <h2>Heureux de vous revoir</h2><p>Connectez-vous pour voir les priorités du jour.</p>
        <?php if ($error): ?><div class="notice notice--error"><?= h($error) ?></div><?php endif; ?>
        <?= csrf_field() ?>
        <div class="field"><label for="email">Adresse e-mail</label><input id="email" name="email" type="email" required autocomplete="username" value="<?= h(post_string('email', '')) ?>" placeholder="nom@raja.local"></div>
        <div class="field"><label for="password">Mot de passe</label><input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Votre mot de passe"></div>
        <button class="button button--primary" type="submit">Se connecter</button>
        <div class="login-help">Utilisez votre compte nominatif. Lors d'une nouvelle installation, créez d'abord le premier compte administrateur avec <code>scripts/create_admin.php</code>.</div>
    </form>
</section>
</body>
</html>
