<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();

$pdo = db();
sync_automatic_statuses($pdo, (int) current_user()['id']);

$counts = array_fill_keys(valid_statuses(), 0);
foreach ($pdo->query('SELECT statut, COUNT(*) AS total FROM dossiers GROUP BY statut') as $row) {
    $counts[$row['statut']] = (int) $row['total'];
}

$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+7 days'));
$summary = [
    'today' => (int) $pdo->query("SELECT COUNT(*) FROM dossiers WHERE statut = 'a_facturer' AND date_prevue_facturation = CURDATE()")->fetchColumn(),
    'overdue' => (int) $pdo->query("SELECT COUNT(*) FROM dossiers WHERE statut = 'a_facturer' AND date_prevue_facturation < CURDATE()")->fetchColumn(),
    'card' => $counts['carte_vitale_a_recuperer'],
    'stock' => $counts['attente_stock'],
    'sav' => $counts['sav'],
    'problem' => $counts['problematique'],
    'soon' => (int) $pdo->query("SELECT COUNT(*) FROM dossiers WHERE statut IN ('dossier_en_cours','carte_vitale_a_recuperer') AND date_prevue_facturation BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn(),
    'warning' => (int) $pdo->query("SELECT COUNT(*) FROM dossiers WHERE import_warning IS NOT NULL AND import_warning <> ''")->fetchColumn(),
];

$priorities = $pdo->query("SELECT d.*, c.nom, c.prenom FROM dossiers d JOIN clients c ON c.id=d.client_id WHERE d.statut IN ('a_facturer','carte_vitale_a_recuperer','sav','problematique') OR d.import_warning IS NOT NULL OR (d.statut='attente_stock' AND d.created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)) OR (d.commentaire IS NOT NULL AND d.commentaire <> '' AND d.priority IN ('haute','urgente')) ORDER BY FIELD(d.priority,'urgente','haute','normale','basse'), COALESCE(d.date_prevue_facturation, '9999-12-31') ASC LIMIT 12")->fetchAll();
$recent = $pdo->query("SELECT h.*, u.name AS user_name FROM history h LEFT JOIN users u ON u.id=h.user_id ORDER BY h.created_at DESC LIMIT 8")->fetchAll();

$pageTitle = 'Tableau de bord';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="cards" aria-label="Résumé des dossiers">
<?php
$cards = [
 ['dossier_en_cours','⌁','Dossiers en cours','/pages/dossiers.php?statut=dossier_en_cours','#176bff','#eaf2ff'],
 ['carte_vitale_a_recuperer','✚','Carte vitale','/pages/carte_vitale.php','#b85d0b','#fff0dc'],
 ['a_facturer','€','À facturer','/pages/a_facturer.php','#b85d0b','#fff0dc'],
 ['facture','✓','Facturés','/pages/factures.php','#138a5b','#e4f7ee'],
 ['sav','↻','SAV','/pages/sav.php','#7650c8','#f0eaff'],
 ['problematique','!','Problématiques','/pages/problematiques.php','#c53845','#ffeaec'],
 ['attente_stock','□','Attente stock','/pages/attente_stock.php','#66758a','#edf1f5'],
];
foreach ($cards as [$status,$icon,$label,$path,$accent,$tone]): ?>
    <a class="stat-card" href="<?= h(url($path)) ?>" style="--accent:<?= $accent ?>;--tone:<?= $tone ?>"><span class="stat-card__icon"><?= $icon ?></span><strong><?= (int) ($counts[$status] ?? 0) ?></strong><span><?= h($label) ?></span></a>
<?php endforeach; ?>
</section>

<section class="assistant">
    <div class="assistant__top"><span class="assistant__spark">✦</span><div><h2>Bonjour, voici les priorités du jour</h2><p>Raja a recalculé les échéances et regroupé les actions qui demandent votre attention.</p></div></div>
    <div class="assistant__stats">
        <div class="assistant__stat"><strong><?= $summary['today'] ?></strong><span>à facturer aujourd’hui</span></div>
        <div class="assistant__stat"><strong><?= $summary['overdue'] ?></strong><span>en retard</span></div>
        <div class="assistant__stat"><strong><?= $summary['card'] ?></strong><span>cartes vitales</span></div>
        <div class="assistant__stat"><strong><?= $summary['stock'] ?></strong><span>attentes stock</span></div>
        <div class="assistant__stat"><strong><?= $summary['sav'] ?></strong><span>SAV actifs</span></div>
        <div class="assistant__stat"><strong><?= $summary['problem'] ?></strong><span>problématiques</span></div>
        <div class="assistant__stat"><strong><?= $summary['soon'] ?></strong><span>bientôt à facturer</span></div>
        <div class="assistant__stat"><strong><?= $summary['warning'] ?></strong><span>incohérences import</span></div>
    </div>
    <div class="assistant__actions"><a class="button" href="<?= h(url('/pages/a_facturer.php')) ?>">Voir à facturer</a><a class="button" href="<?= h(url('/pages/carte_vitale.php')) ?>">Voir cartes vitales</a><a class="button" href="<?= h(url('/pages/problematiques.php')) ?>">Voir problèmes</a><a class="button" href="<?= h(url('/pages/sav.php')) ?>">Voir SAV</a><a class="button" href="<?= h(url('/pages/attente_stock.php')) ?>">Voir le stock</a></div>
</section>

<div class="grid-2">
    <section class="panel">
        <div class="panel__header"><h2>Priorités à traiter</h2><a class="button button--secondary button--small" href="<?= h(url('/pages/dossiers.php')) ?>">Tous les dossiers</a></div>
        <div class="table-wrap">
            <table class="data-table"><thead><tr><th>Client</th><th>Statut</th><th>Départ</th><th>Échéance</th><th>Commentaire</th><th></th></tr></thead><tbody>
            <?php foreach ($priorities as $row): $metrics = delay_metrics($row); ?>
                <tr><td class="cell-main"><strong><?= h($row['nom'] . ' ' . $row['prenom']) ?></strong><small>Dossier #<?= (int) $row['id'] ?></small></td><td><span class="<?= status_class($row['statut']) ?>"><?= h(status_label($row['statut'])) ?></span></td><td><?= date_fr($row['date_depart_delai']) ?></td><td class="<?= $row['date_prevue_facturation'] && $row['date_prevue_facturation'] < $today && $row['statut'] !== 'facture' ? 'overdue' : '' ?>"><?= date_fr($row['date_prevue_facturation']) ?></td><td class="cell-main"><small><?= h(mb_strimwidth($row['commentaire'] ?: $row['probleme_commentaire'] ?: '—', 0, 55, '…')) ?></small></td><td><a class="button button--secondary button--small" href="<?= h(url('/pages/dossier_view.php?id=' . $row['id'])) ?>">Ouvrir</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$priorities): ?><tr><td colspan="6"><div class="empty-state"><strong>Tout est à jour</strong>Aucune priorité particulière aujourd’hui.</div></td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </section>
    <section class="panel">
        <div class="panel__header"><h2>Activité récente</h2><a class="button button--secondary button--small" href="<?= h(url('/pages/historique.php')) ?>">Historique</a></div>
        <div class="panel__body timeline">
            <?php foreach ($recent as $event): ?><div class="timeline__item"><strong><?= h(str_replace('_', ' ', ucfirst($event['action']))) ?></strong><p><?= h(mb_strimwidth($event['details'] ?: 'Action enregistrée', 0, 80, '…')) ?></p><small><?= h($event['user_name'] ?: 'Système') ?> · <?= date_fr($event['created_at']) ?> à <?= h(date('H:i', strtotime($event['created_at']))) ?></small></div><?php endforeach; ?>
        </div>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
