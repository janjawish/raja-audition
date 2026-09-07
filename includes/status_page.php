<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();
$pdo=db();
$stmt=$pdo->prepare("SELECT d.*,c.nom,c.prenom FROM dossiers d JOIN clients c ON c.id=d.client_id WHERE d.statut=? ORDER BY FIELD(d.priority,'urgente','haute','normale','basse'),COALESCE(d.date_prevue_facturation,'9999-12-31') ASC,d.updated_at DESC");
$stmt->execute([$pageStatus]);$dossiers=$stmt->fetchAll();
$pageEyebrow=$pageDescription;require dirname(__DIR__).'/includes/header.php';
?>
<div class="page-actions"><p class="muted" style="margin:0"><?= h($pageIntro) ?></p><a class="button button--primary" href="<?= h(url('/pages/dossier_form.php')) ?>">+ Nouveau dossier</a></div>
<?php $listTitle=$pageTitle;require dirname(__DIR__).'/includes/dossier_table.php'; ?>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
