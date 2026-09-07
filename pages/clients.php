<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();

$search = trim((string) ($_GET['q'] ?? ''));
$sql = "SELECT c.*, COUNT(d.id) AS dossier_count, MAX(d.updated_at) AS last_activity FROM clients c LEFT JOIN dossiers d ON d.client_id=c.id";
$params = [];
if ($search !== '') {
    $sql .= ' WHERE c.nom LIKE ? OR c.prenom LIKE ? OR c.telephone LIKE ? OR c.email LIKE ?';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' GROUP BY c.id ORDER BY c.nom, c.prenom LIMIT 300';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$pageTitle = 'Clients';
$pageEyebrow = count($clients) . ' fiche' . (count($clients) > 1 ? 's' : '');
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-actions">
    <form class="search" method="get"><input class="search-input" name="q" value="<?= h($search) ?>" placeholder="Rechercher un client…" aria-label="Rechercher un client"></form>
    <div class="page-actions__group"><a class="button button--secondary" href="<?= h(url('/pages/import_cosium.php')) ?>">◫ Importer un PDF Cosium</a><a class="button button--primary" href="<?= h(url('/pages/client_form.php')) ?>">+ Créer un client</a></div>
</div>
<section class="panel">
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Client</th><th>Coordonnées</th><th>Dossiers</th><th>Dernière activité</th><th></th></tr></thead><tbody>
    <?php foreach ($clients as $client): ?>
        <tr><td class="cell-main"><strong><?= h($client['nom'] . ' ' . $client['prenom']) ?></strong><small>Client #<?= (int) $client['id'] ?></small></td><td class="cell-main"><span><?= h($client['telephone'] ?: '—') ?></span><small><?= h($client['email'] ?: 'Aucun e-mail') ?></small></td><td><strong><?= (int) $client['dossier_count'] ?></strong></td><td><?= date_fr($client['last_activity'] ?: $client['updated_at']) ?></td><td class="cell-actions"><a class="button button--secondary button--small" href="<?= h(url('/pages/client_view.php?id=' . $client['id'])) ?>">Ouvrir</a><a class="button button--secondary button--small" href="<?= h(url('/pages/client_form.php?id=' . $client['id'])) ?>">Modifier</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$clients): ?><tr><td colspan="5"><div class="empty-state"><strong>Aucun client trouvé</strong>Créez une première fiche ou modifiez votre recherche.</div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
