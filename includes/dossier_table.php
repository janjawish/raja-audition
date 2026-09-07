<?php

declare(strict_types=1);

$todayForTable = date('Y-m-d');
?>
<section class="panel">
    <?php if (!empty($listTitle)): ?><div class="panel__header"><h2><?= h($listTitle) ?></h2><span class="muted"><?= count($dossiers) ?> dossier<?= count($dossiers) > 1 ? 's' : '' ?></span></div><?php endif; ?>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Client / dossier</th><th>Statut</th><th>Priorité</th><th>Date départ</th><th>Carte vitale</th><th>Facturation</th><th>Reste</th><th></th></tr></thead><tbody>
    <?php foreach ($dossiers as $dossier): $metrics = delay_metrics($dossier); $isOverdue = $dossier['date_prevue_facturation'] && $dossier['date_prevue_facturation'] < $todayForTable && !in_array($dossier['statut'], ['facture','annule','sav'], true); ?>
        <tr><td class="cell-main"><strong><?= h($dossier['nom'] . ' ' . $dossier['prenom']) ?></strong><small>Dossier #<?= (int) $dossier['id'] ?> · <?= money_fr($dossier['total_ttc']) ?></small></td><td><span class="<?= status_class($dossier['statut']) ?>"><?= h(status_label($dossier['statut'])) ?></span></td><td><span class="<?= priority_class($dossier['priority']) ?>"><?= h(priority_label($dossier['priority'])) ?></span></td><td><?= date_fr($dossier['date_depart_delai']) ?></td><td><?= date_fr($dossier['date_prevue_carte_vitale']) ?></td><td class="<?= $isOverdue ? 'overdue' : (($metrics['raw_remaining'] ?? 99) <= 7 ? 'soon' : '') ?>"><?= date_fr($dossier['date_prevue_facturation']) ?></td><td><?= $metrics['active'] ? (($metrics['raw_remaining'] < 0) ? '<span class="overdue">Retard ' . abs($metrics['raw_remaining']) . ' j</span>' : (int) $metrics['remaining'] . ' j') : '—' ?></td><td><a class="button button--secondary button--small" href="<?= h(url('/pages/dossier_view.php?id=' . $dossier['id'])) ?>">Ouvrir</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$dossiers): ?><tr><td colspan="8"><div class="empty-state"><strong>Aucun dossier dans cette liste</strong>Les dossiers apparaîtront ici dès qu’ils correspondront à ce statut.</div></td></tr><?php endif; ?>
    </tbody></table></div>
</section>
