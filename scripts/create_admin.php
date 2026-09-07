<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

[$script, $email, $password, $name] = array_pad($argv, 4, '');
$email = mb_strtolower(trim((string) $email));
$password = (string) $password;
$name = trim((string) $name) ?: 'Administrateur Raja';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 12) {
    fwrite(STDERR, "Usage : php scripts/create_admin.php email@example.fr MotDePasseSolide [Nom]\n");
    fwrite(STDERR, "Le mot de passe doit contenir au moins 12 caractères.\n");
    exit(2);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
if ($stmt->fetchColumn()) {
    fwrite(STDERR, "Un compte existe déjà pour cette adresse e-mail.\n");
    exit(1);
}

$stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, \'admin\', 1)');
$stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
echo "Compte administrateur créé pour {$email}.\n";
