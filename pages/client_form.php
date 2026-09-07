<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();

$id = (int) ($_GET['id'] ?? 0);
$client = ['id' => 0, 'nom' => '', 'prenom' => '', 'telephone' => '', 'email' => '', 'notes' => '', 'cosium_numero' => '', 'date_naissance' => '', 'adresse' => '', 'caisse_secu' => '', 'numero_securite_sociale' => '', 'complementaire' => '', 'assure_nom' => '', 'date_creation_cosium' => ''];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM clients WHERE id = ?');
    $stmt->execute([$id]);
    $client = $stmt->fetch() ?: $client;
    if (!$client['id']) { http_response_code(404); exit('Client introuvable.'); }
}

$pageTitle = $id ? 'Modifier le client' : 'Nouveau client';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-actions"><a class="button button--secondary" href="<?= h(url($id ? '/pages/client_view.php?id=' . $id : '/pages/clients.php')) ?>">← Retour</a></div>
<form method="post" action="<?= h(url('/actions/client_save.php')) ?>">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
    <section class="panel" style="margin-bottom:22px"><div class="panel__header"><h2>Identité et coordonnées</h2></div><div class="panel__body form-grid">
        <div class="field"><label for="nom">Nom *</label><input id="nom" name="nom" required maxlength="120" value="<?= h($client['nom']) ?>" autocomplete="family-name"></div>
        <div class="field"><label for="prenom">Prénom *</label><input id="prenom" name="prenom" required maxlength="120" value="<?= h($client['prenom']) ?>" autocomplete="given-name"></div>
        <div class="field"><label for="telephone">Téléphone</label><input id="telephone" name="telephone" maxlength="40" value="<?= h($client['telephone']) ?>" autocomplete="tel"></div>
        <div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" maxlength="190" value="<?= h($client['email']) ?>" autocomplete="email"></div>
        <div class="field field--full"><label for="notes">Notes</label><textarea id="notes" name="notes" maxlength="5000" placeholder="Informations utiles pour l’équipe…"><?= h($client['notes']) ?></textarea></div>
    </div></section>
    <section class="panel"><div class="panel__header"><h2>Données Cosium</h2><a class="button button--secondary button--small" href="<?= h(url('/pages/import_cosium.php')) ?>">Lire un PDF</a></div><div class="panel__body form-grid form-grid--3">
        <div class="field"><label for="cosium_numero">Numéro Cosium</label><input id="cosium_numero" name="cosium_numero" maxlength="50" value="<?= h($client['cosium_numero']) ?>"></div>
        <div class="field"><label for="date_creation_cosium">Fiche créée dans Cosium le</label><input id="date_creation_cosium" name="date_creation_cosium" type="date" value="<?= h($client['date_creation_cosium']) ?>"></div>
        <div class="field"><label for="date_naissance">Date de naissance</label><input id="date_naissance" name="date_naissance" type="date" value="<?= h($client['date_naissance']) ?>"></div>
        <div class="field field--full"><label for="adresse">Adresse</label><textarea id="adresse" name="adresse" maxlength="2000"><?= h($client['adresse']) ?></textarea></div>
        <div class="field"><label for="caisse_secu">Caisse de sécurité sociale</label><input id="caisse_secu" name="caisse_secu" maxlength="190" value="<?= h($client['caisse_secu']) ?>"></div>
        <div class="field"><label for="numero_securite_sociale">Numéro de sécurité sociale</label><input id="numero_securite_sociale" name="numero_securite_sociale" maxlength="30" value="<?= h($client['numero_securite_sociale']) ?>" autocomplete="off"></div>
        <div class="field"><label for="complementaire">Complémentaire</label><input id="complementaire" name="complementaire" maxlength="190" value="<?= h($client['complementaire']) ?>"></div>
        <div class="field"><label for="assure_nom">Assuré</label><input id="assure_nom" name="assure_nom" maxlength="190" value="<?= h($client['assure_nom']) ?>"></div>
    </div><div class="panel__footer page-actions"><span class="muted">* Champs obligatoires</span><button class="button button--primary" type="submit">Enregistrer le client</button></div></section>
</form>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
