<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['statut'] ?? ''));
$priority = trim((string) ($_GET['priority'] ?? ''));
$where = [];
$params = [];
if ($search !== '') { $where[] = '(c.nom LIKE ? OR c.prenom LIKE ? OR d.commentaire LIKE ? OR CAST(d.id AS CHAR) = ?)'; $like = '%' . $search . '%'; array_push($params, $like, $like, $like, $search); }
if (in_array($status, valid_statuses(), true)) { $where[] = 'd.statut = ?'; $params[] = $status; }
if (in_array($priority, ['basse','normale','haute','urgente'], true)) { $where[] = 'd.priority = ?'; $params[] = $priority; }
$sql = 'SELECT d.*, c.nom, c.prenom FROM dossiers d JOIN clients c ON c.id=d.client_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY FIELD(d.priority,'urgente','haute','normale','basse'), d.updated_at DESC LIMIT 400";
$stmt = db()->prepare($sql); $stmt->execute($params); $dossiers = $stmt->fetchAll();

$pageTitle = 'Dossiers audition';
$pageEyebrow = count($dossiers) . ' résultat' . (count($dossiers) > 1 ? 's' : '');
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-actions">
    <form class="page-actions__group" method="get"><div class="search"><input class="search-input" name="q" value="<?= h($search) ?>" placeholder="Client, commentaire, n°…"></div><select class="search-input" name="statut" aria-label="Filtrer par statut"><option value="">Tous les statuts</option><?php foreach (valid_statuses() as $option): ?><option value="<?= h($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= h(status_label($option)) ?></option><?php endforeach; ?></select><select class="search-input" name="priority" aria-label="Filtrer par priorité"><option value="">Toutes priorités</option><?php foreach (['urgente','haute','normale','basse'] as $option): ?><option value="<?= $option ?>" <?= $priority === $option ? 'selected' : '' ?>><?= h(priority_label($option)) ?></option><?php endforeach; ?></select><button class="button button--secondary" type="submit">Filtrer</button></form>
    <a class="button button--primary" href="<?= h(url('/pages/dossier_form.php')) ?>">+ Créer un dossier</a>
</div>
<?php $listTitle = null; require dirname(__DIR__) . '/includes/dossier_table.php'; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
