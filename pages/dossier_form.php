<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$clientId = (int) ($_GET['client_id'] ?? 0);
$dossier = [
    'id'=>0,'client_id'=>$clientId,'ordo_status'=>'attente','date_ordo'=>'','mutuelle_css'=>'','date_appareillage'=>date('Y-m-d'),'total_ttc'=>'0.00','pec_faite'=>0,'pec_faite_le'=>'','facturer_a_partir_de'=>'','facturation_status'=>'non','facturation_faite_le'=>'','commentaire'=>'','statut'=>'dossier_en_cours','appareil_en_stock'=>1,'date_arrivee_stock'=>'','date_depart_delai'=>date('Y-m-d'),'priority'=>'normale'
];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM dossiers WHERE id=?'); $stmt->execute([$id]); $record=$stmt->fetch();
    if (!$record) { http_response_code(404); exit('Dossier introuvable.'); }
    $dossier = array_merge($dossier, $record);
}
$clients = $pdo->query('SELECT id, nom, prenom FROM clients ORDER BY nom, prenom')->fetchAll();
$pageTitle = $id ? 'Modifier le dossier #' . $id : 'Nouveau dossier audition';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-actions"><a class="button button--secondary" href="<?= h(url($id ? '/pages/dossier_view.php?id=' . $id : '/pages/dossiers.php')) ?>">← Retour</a></div>
<form method="post" action="<?= h(url('/actions/dossier_save.php')) ?>">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $dossier['id'] ?>">
<section class="panel" style="margin-bottom:22px"><div class="panel__header"><h2>Client et suivi</h2></div><div class="panel__body form-grid form-grid--3">
    <div class="field field--full"><label for="client_id">Client *</label><select id="client_id" name="client_id" required><option value="">Sélectionner un client</option><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>" <?= (int)$dossier['client_id']===(int)$client['id']?'selected':'' ?>><?= h($client['nom'].' '.$client['prenom']) ?></option><?php endforeach; ?></select><small>Le client n’existe pas ? <a href="<?= h(url('/pages/client_form.php')) ?>">Créer sa fiche d’abord</a>.</small></div>
    <div class="field"><label for="priority">Priorité</label><select id="priority" name="priority"><?php foreach(['basse','normale','haute','urgente'] as $p): ?><option value="<?= $p ?>" <?= $dossier['priority']===$p?'selected':'' ?>><?= h(priority_label($p)) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="ordo_status">Ordonnance</label><select id="ordo_status" name="ordo_status"><?php foreach(['oui'=>'Oui','non'=>'Non','attente'=>'En attente'] as $k=>$v): ?><option value="<?= $k ?>" <?= $dossier['ordo_status']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="date_ordo">Date ordonnance</label><input id="date_ordo" name="date_ordo" type="date" value="<?= h($dossier['date_ordo']) ?>"></div>
    <div class="field"><label for="mutuelle_css">Mutuelle / CSS</label><input id="mutuelle_css" name="mutuelle_css" maxlength="190" value="<?= h($dossier['mutuelle_css']) ?>"></div>
    <div class="field"><label for="date_appareillage">Date appareillage</label><input id="date_appareillage" name="date_appareillage" type="date" value="<?= h($dossier['date_appareillage']) ?>"></div>
    <div class="field"><label for="total_ttc">Total TTC (€)</label><input id="total_ttc" name="total_ttc" type="number" min="0" step="0.01" value="<?= h($dossier['total_ttc']) ?>"></div>
</div></section>
<div class="grid-equal" style="margin-bottom:22px">
<section class="panel"><div class="panel__header"><h2>Stock et délai 30 jours</h2></div><div class="panel__body form-grid">
    <div class="field"><label for="appareil_en_stock">Appareil disponible ?</label><select id="appareil_en_stock" name="appareil_en_stock" data-stock-toggle><option value="1" <?= (int)$dossier['appareil_en_stock']===1?'selected':'' ?>>Oui, en stock</option><option value="0" <?= (int)$dossier['appareil_en_stock']===0?'selected':'' ?>>Non, en attente stock</option></select></div>
    <div class="field"><label for="date_depart_delai">Date de départ du délai</label><input id="date_depart_delai" name="date_depart_delai" type="date" value="<?= h($dossier['date_depart_delai']) ?>"><small>Requise si l’appareil est disponible.</small></div>
    <div class="field field--full" data-stock-dependent><label for="date_arrivee_stock">Date d’arrivée stock</label><input id="date_arrivee_stock" name="date_arrivee_stock" type="date" value="<?= h($dossier['date_arrivee_stock']) ?>"><small>Laissez vide tant que l’appareil n’est pas arrivé.</small></div>
</div></section>
<section class="panel"><div class="panel__header"><h2>PEC et facturation</h2></div><div class="panel__body form-grid">
    <div class="field"><label for="pec_faite">PEC faite ?</label><select id="pec_faite" name="pec_faite"><option value="0" <?= !(int)$dossier['pec_faite']?'selected':'' ?>>Non</option><option value="1" <?= (int)$dossier['pec_faite']?'selected':'' ?>>Oui</option></select></div>
    <div class="field"><label for="pec_faite_le">PEC faite le</label><input id="pec_faite_le" name="pec_faite_le" type="date" value="<?= h($dossier['pec_faite_le']) ?>"></div>
    <div class="field"><label for="facturer_a_partir_de">Facturer à partir de</label><input id="facturer_a_partir_de" name="facturer_a_partir_de" type="date" value="<?= h($dossier['facturer_a_partir_de']) ?>"></div>
    <div class="field"><label for="facturation_status">Déjà facturé ?</label><select id="facturation_status" name="facturation_status"><option value="non" <?= $dossier['facturation_status']==='non'?'selected':'' ?>>Non</option><option value="oui" <?= $dossier['facturation_status']==='oui'?'selected':'' ?>>Oui</option></select></div>
    <div class="field field--full"><label for="facturation_faite_le">Facturation faite le</label><input id="facturation_faite_le" name="facturation_faite_le" type="date" value="<?= h($dossier['facturation_faite_le']) ?>"></div>
</div></section>
</div>
<section class="panel"><div class="panel__header"><h2>Commentaire d’équipe</h2></div><div class="panel__body"><div class="field"><label for="commentaire">Commentaire</label><textarea id="commentaire" name="commentaire" maxlength="10000" placeholder="Prochaine action, information importante, relance…"><?= h($dossier['commentaire']) ?></textarea></div></div><div class="panel__footer page-actions"><span class="muted">Raja ne facture jamais automatiquement.</span><button class="button button--primary" type="submit">Enregistrer le dossier</button></div></section>
</form>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
