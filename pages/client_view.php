<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM clients WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) { http_response_code(404); exit('Client introuvable.'); }
$dossierStmt = db()->prepare('SELECT * FROM dossiers WHERE client_id = ? ORDER BY created_at DESC');
$dossierStmt->execute([$id]);
$dossiers = $dossierStmt->fetchAll();
$cosiumStmt = db()->prepare('SELECT * FROM cosium_dossiers WHERE client_id = ? ORDER BY dossier_date DESC, id DESC');
$cosiumStmt->execute([$id]);
$cosiumDossiers = $cosiumStmt->fetchAll();

$pageTitle = $client['nom'] . ' ' . $client['prenom'];
$pageEyebrow = 'Fiche client #' . $id;
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-actions"><a class="button button--secondary" href="<?= h(url('/pages/clients.php')) ?>">← Clients</a><div class="page-actions__group"><a class="button button--secondary" href="<?= h(url('/pages/client_form.php?id=' . $id)) ?>">Modifier la fiche</a><a class="button button--primary" href="<?= h(url('/pages/dossier_form.php?client_id=' . $id)) ?>">+ Nouveau dossier</a></div></div>
<div class="grid-2">
    <section class="panel"><div class="panel__header"><h2>Dossiers audition</h2><span class="badge badge--dossier-en-cours"><?= count($dossiers) ?> dossier<?= count($dossiers) > 1 ? 's' : '' ?></span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>N°</th><th>Statut</th><th>Appareillage</th><th>Facturation prévue</th><th>Total</th><th></th></tr></thead><tbody>
        <?php foreach ($dossiers as $dossier): ?><tr><td><strong>#<?= (int) $dossier['id'] ?></strong></td><td><span class="<?= status_class($dossier['statut']) ?>"><?= h(status_label($dossier['statut'])) ?></span></td><td><?= date_fr($dossier['date_appareillage']) ?></td><td><?= date_fr($dossier['date_prevue_facturation']) ?></td><td><?= money_fr($dossier['total_ttc']) ?></td><td><a class="button button--secondary button--small" href="<?= h(url('/pages/dossier_view.php?id=' . $dossier['id'])) ?>">Ouvrir</a></td></tr><?php endforeach; ?>
        <?php if (!$dossiers): ?><tr><td colspan="6"><div class="empty-state"><strong>Aucun dossier</strong>Créez le premier dossier audition de ce client.</div></td></tr><?php endif; ?>
    </tbody></table></div></section>
    <aside style="display:grid;align-content:start;gap:22px"><section class="panel"><div class="panel__header"><h2>Coordonnées</h2></div><div class="panel__body"><dl class="detail-list"><div><dt>Téléphone</dt><dd><?= h($client['telephone'] ?: '—') ?></dd></div><div><dt>E-mail</dt><dd><?= h($client['email'] ?: '—') ?></dd></div><div><dt>Date de naissance</dt><dd><?= date_fr($client['date_naissance']) ?></dd></div><div><dt>Adresse</dt><dd><?= h($client['adresse'] ?: '—') ?></dd></div><div><dt>Créé le</dt><dd><?= date_fr($client['created_at']) ?></dd></div><div><dt>Mis à jour</dt><dd><?= date_fr($client['updated_at']) ?></dd></div></dl><?php if ($client['notes']): ?><h3>Notes</h3><p><?= nl2br(h($client['notes'])) ?></p><?php endif; ?></div></section>
    <?php if ($client['cosium_numero'] || $client['date_creation_cosium'] || $cosiumDossiers): ?><section class="panel"><div class="panel__header"><h2>Cosium</h2><a class="button button--secondary button--small" href="<?= h(url('/pages/import_cosium.php')) ?>">Importer un PDF</a></div><div class="panel__body"><dl class="detail-list"><div><dt>Numéro</dt><dd><?= h($client['cosium_numero'] ?: '—') ?></dd></div><div><dt>Fiche créée le</dt><dd><?= date_fr($client['date_creation_cosium']) ?></dd></div><div><dt>Caisse</dt><dd><?= h($client['caisse_secu'] ?: '—') ?></dd></div><div><dt>N° sécurité sociale</dt><dd><?= h(mask_social_number($client['numero_securite_sociale'])) ?></dd></div><div><dt>Complémentaire</dt><dd><?= h($client['complementaire'] ?: '—') ?></dd></div><div><dt>Assuré</dt><dd><?= h($client['assure_nom'] ?: '—') ?></dd></div></dl></div><?php if($cosiumDossiers):?><div class="panel__footer"><strong>Dossiers trouvés dans Cosium</strong><?php foreach($cosiumDossiers as $cosium):?><div style="margin-top:10px"><span class="badge badge--dossier-en-cours"><?= h($cosium['dossier_type']) ?></span> <span><?= date_fr($cosium['dossier_date']) ?></span><?php if($cosium['raja_dossier_id']):?> · <a href="<?= h(url('/pages/dossier_view.php?id='.$cosium['raja_dossier_id'])) ?>">Dossier Raja #<?= (int)$cosium['raja_dossier_id'] ?></a><?php endif;?><small class="muted" style="display:block;margin-top:4px"><?= h(mb_strimwidth($cosium['details'] ?: 'Aucun détail',0,120,'…')) ?></small></div><?php endforeach;?></div><?php endif;?></section><?php endif;?></aside>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
