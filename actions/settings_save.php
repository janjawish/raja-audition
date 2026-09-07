<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';require_roles(['admin','patron']);if($_SERVER['REQUEST_METHOD']!=='POST')redirect('/pages/parametres.php');verify_csrf();
$shop=mb_substr(post_string('shop_name','Raja Audition')??'Raja Audition',0,150);$days=max(1,min(365,(int)($_POST['stock_warning_days']??14)));$pdo=db();$s=$pdo->prepare('UPDATE settings SET setting_value=?,updated_at=NOW() WHERE setting_key=?');$s->execute([$shop,'shop_name']);$s->execute([(string)$days,'stock_warning_days']);log_action($pdo,'settings',1,'modification_parametres','Boutique : '.$shop.' · alerte stock : '.$days.' jours');flash('success','Les paramètres ont été enregistrés.');redirect('/pages/parametres.php');
