<?php

declare(strict_types=1);

$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$counts = [];
try {
    foreach (db()->query('SELECT statut, COUNT(*) AS total FROM dossiers GROUP BY statut') as $row) {
        $counts[$row['statut']] = (int) $row['total'];
    }
} catch (Throwable) {
    $counts = [];
}

$items = [
    ['/pages/dashboard.php', '⌂', 'Tableau de bord', null, []],
    ['/pages/clients.php', '♙', 'Clients', null, []],
    ['/pages/dossiers.php', '▤', 'Dossiers', null, []],
    ['/pages/attente_stock.php', '□', 'Attente stock', $counts['attente_stock'] ?? 0, []],
    ['/pages/carte_vitale.php', '✚', 'Carte vitale', $counts['carte_vitale_a_recuperer'] ?? 0, []],
    ['/pages/a_facturer.php', '€', 'À facturer', $counts['a_facturer'] ?? 0, []],
    ['/pages/factures.php', '✓', 'Facturés', $counts['facture'] ?? 0, []],
    ['/pages/sav.php', '↻', 'SAV', $counts['sav'] ?? 0, []],
    ['/pages/problematiques.php', '!', 'Problématiques', $counts['problematique'] ?? 0, []],
    ['/pages/import_excel.php', '⇧', 'Import Excel / CSV', null, []],
    ['/pages/import_cosium.php', '◫', 'Import PDF Cosium', null, []],
    ['/pages/historique.php', '◷', 'Historique', null, []],
    ['/pages/parametres.php', '⚙', 'Paramètres', null, ['admin', 'patron']],
    ['/pages/users.php', '♚', 'Utilisateurs', null, ['admin']],
];
?>
<aside class="sidebar" data-sidebar>
    <div class="brand">
        <span class="brand__mark">R</span>
        <div><strong>Raja</strong><small>Suivi audition</small></div>
        <button class="icon-button sidebar-close" type="button" aria-label="Fermer le menu" data-menu-toggle>×</button>
    </div>
    <nav class="nav" aria-label="Navigation principale">
        <?php foreach ($items as [$path, $icon, $label, $count, $roles]): ?>
            <?php if ($roles && !in_array($user['role'] ?? '', $roles, true)) continue; ?>
            <a class="nav__item <?= str_ends_with($currentPath, $path) ? 'is-active' : '' ?>" href="<?= h(url($path)) ?>">
                <span class="nav__icon"><?= $icon ?></span><span><?= h($label) ?></span>
                <?php if ($count !== null && $count > 0): ?><span class="nav__count"><?= $count ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar__footer">
        <div class="status-dot"><span></span> Données locales</div>
        <a href="<?= h(url('/logout.php')) ?>">Se déconnecter</a>
    </div>
</aside>
<div class="sidebar-backdrop" data-menu-toggle></div>
