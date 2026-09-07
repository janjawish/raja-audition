<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';require_auth();$pdo=db();
$dossiers=$pdo->query("SELECT d.*,c.nom,c.prenom FROM dossiers d JOIN clients c ON c.id=d.client_id WHERE d.statut='problematique' ORDER BY FIELD(d.priority,'urgente','haute','normale','basse'),d.updated_at ASC")->fetchAll();
$pageTitle='Problématiques';$pageEyebrow=count($dossiers).' dossier'.(count($dossiers)>1?'s':'').' à résoudre';require dirname(__DIR__).'/includes/header.php';
?>
<div class="page-actions"><p class="muted">Les problèmes sont isolés des échéances automatiques jusqu’à leur résolution.</p><a class="button button--primary" href="<?= h(url('/pages/dossiers.php')) ?>">Voir tous les dossiers</a></div>
<section class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Client</th><th>Problème</th><th>Commentaire</th><th>Priorité</th><th>Action à faire</th><th>Date</th><th></th></tr></thead><tbody>
<?php foreach($dossiers as $d):?><tr><td class="cell-main"><strong><?= h($d['nom'].' '.$d['prenom']) ?></strong><small>Dossier #<?= (int)$d['id'] ?></small></td><td><span class="badge badge--problematique"><?= h(ucfirst($d['probleme_type'])) ?></span></td><td><?= h(mb_strimwidth($d['probleme_commentaire']?:'—',0,75,'…')) ?></td><td><span class="<?= priority_class($d['priority']) ?>"><?= h(priority_label($d['priority'])) ?></span></td><td><?= h($d['probleme_action']?:'À définir') ?></td><td><?= date_fr($d['updated_at']) ?></td><td><a class="button button--secondary button--small" href="<?= h(url('/pages/dossier_view.php?id='.$d['id'])) ?>">Ouvrir</a></td></tr><?php endforeach;?>
<?php if(!$dossiers):?><tr><td colspan="7"><div class="empty-state"><strong>Aucun problème en cours</strong>Les dossiers signalés apparaîtront ici.</div></td></tr><?php endif;?></tbody></table></div></section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
