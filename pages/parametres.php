<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';require_roles(['admin','patron']);$settings=[];foreach(db()->query('SELECT * FROM settings ORDER BY id') as $row)$settings[$row['setting_key']]=$row;
$pageTitle='Paramètres';$pageEyebrow='Règles de fonctionnement';require dirname(__DIR__).'/includes/header.php';
?>
<div class="grid-2"><form method="post" action="<?= h(url('/actions/settings_save.php')) ?>"><section class="panel"><div class="panel__header"><h2>Réglages Raja</h2></div><div class="panel__body form-grid"><?= csrf_field() ?><div class="field field--full"><label>Nom de la boutique</label><input name="shop_name" value="<?= h($settings['shop_name']['setting_value']??'Raja Audition') ?>"></div><div class="field"><label>Alerte attente stock après</label><input type="number" min="1" max="365" name="stock_warning_days" value="<?= h($settings['stock_warning_days']['setting_value']??'14') ?>"><small>Nombre de jours</small></div><div class="field"><label>Carte vitale à J+</label><input value="23" disabled><small>Règle métier fixe en V1</small></div><div class="field"><label>À facturer à J+</label><input value="30" disabled><small>Règle métier fixe en V1</small></div></div><div class="panel__footer"><button class="button button--primary">Enregistrer</button></div></section></form>
<aside class="panel"><div class="panel__header"><h2>Rappels importants</h2></div><div class="panel__body"><div class="notice notice--info"><span>Raja calcule les alertes, mais ne réalise jamais la facturation.</span></div><div class="kpi-list"><div class="kpi-row"><span>Carte vitale</span><strong>J+23</strong></div><div class="kpi-row"><span>Facturation</span><strong>J+30</strong></div><div class="kpi-row"><span>SAV</span><strong>Délai en pause</strong></div><div class="kpi-row"><span>Hébergement</span><strong>Réseau local</strong></div></div></div></aside></div>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
