<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/pages/clients.php');
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$nom = mb_strtoupper(post_string('nom', '') ?? '');
$prenom = post_string('prenom', '') ?? '';
$email = post_string('email');
if ($nom === '' || $prenom === '') {
    flash('error', 'Le nom et le prénom sont obligatoires.');
    redirect('/pages/client_form.php' . ($id ? '?id=' . $id : ''));
}
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'L’adresse e-mail n’est pas valide.');
    redirect('/pages/client_form.php' . ($id ? '?id=' . $id : ''));
}

$pdo = db();
if ($id) {
    $stmt = $pdo->prepare('UPDATE clients SET nom=?, prenom=?, telephone=?, email=?, notes=?, cosium_numero=?, date_naissance=?, adresse=?, caisse_secu=?, numero_securite_sociale=?, complementaire=?, assure_nom=?, date_creation_cosium=?, updated_at=NOW() WHERE id=?');
    $stmt->execute([$nom, $prenom, post_string('telephone'), $email, post_string('notes'), post_string('cosium_numero') ?: null, nullable_date(post_string('date_naissance')), post_string('adresse'), post_string('caisse_secu'), post_string('numero_securite_sociale'), post_string('complementaire'), post_string('assure_nom'), nullable_date(post_string('date_creation_cosium')), $id]);
    log_action($pdo, 'client', $id, 'modification_client', $nom . ' ' . $prenom);
    flash('success', 'La fiche client a été mise à jour.');
} else {
    $stmt = $pdo->prepare('INSERT INTO clients (nom, prenom, telephone, email, notes, cosium_numero, date_naissance, adresse, caisse_secu, numero_securite_sociale, complementaire, assure_nom, date_creation_cosium) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$nom, $prenom, post_string('telephone'), $email, post_string('notes'), post_string('cosium_numero') ?: null, nullable_date(post_string('date_naissance')), post_string('adresse'), post_string('caisse_secu'), post_string('numero_securite_sociale'), post_string('complementaire'), post_string('assure_nom'), nullable_date(post_string('date_creation_cosium'))]);
    $id = (int) $pdo->lastInsertId();
    log_action($pdo, 'client', $id, 'creation_client', $nom . ' ' . $prenom);
    flash('success', 'Le client a été créé. Vous pouvez maintenant ouvrir un dossier audition.');
}
redirect('/pages/client_view.php?id=' . $id);
