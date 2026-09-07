<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';require_auth();
$entity=trim((string)($_GET['entity']??''));$action=trim((string)($_GET['action']??''));$where=[];$params=[];
if(in_array($entity,['dossier','client','user','settings','import'],true)){$where[]='h.entity_type=?';$params[]=$entity;}
if($action!==''){$where[]='h.action LIKE ?';$params[]='%'.$action.'%';}
$sql='SELECT h.*,u.name AS user_name FROM history h LEFT JOIN users u ON u.id=h.user_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY h.created_at DESC LIMIT 500';$stmt=db()->prepare($sql);$stmt->execute($params);$events=$stmt->fetchAll();
$pageTitle='Historique';$pageEyebrow='Traçabilité des actions';require dirname(__DIR__).'/includes/header.php';
?>
<div class="page-actions"><form class="page-actions__group" method="get"><select class="search-input" name="entity"><option value="">Toutes les catégories</option><?php foreach(['dossier'=>'Dossiers','client'=>'Clients','user'=>'Utilisateurs','settings'=>'Paramètres','import'=>'Imports'] as $k=>$v):?><option value="<?= $k ?>" <?= $entity===$k?'selected':'' ?>><?= $v ?></option><?php endforeach;?></select><div class="search"><input class="search-input" name="action" value="<?= h($action) ?>" placeholder="Rechercher une action…"></div><button class="button button--secondary">Filtrer</button></form></div>
<section class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Utilisateur</th><th>Catégorie</th><th>Action</th><th>Détails</th><th>Référence</th></tr></thead><tbody>
<?php foreach($events as $e):?><tr><td class="nowrap"><?= date_fr($e['created_at']) ?> · <?= h(date('H:i',strtotime($e['created_at']))) ?></td><td><?= h($e['user_name']?:'Système') ?></td><td><?= h(ucfirst($e['entity_type'])) ?></td><td><strong><?= h(str_replace('_',' ',$e['action'])) ?></strong></td><td><?= h($e['details']?:'—') ?></td><td><?php if($e['entity_type']==='dossier'):?><a class="link" href="<?= h(url('/pages/dossier_view.php?id='.$e['entity_id'])) ?>">#<?= (int)$e['entity_id'] ?></a><?php elseif($e['entity_type']==='client'):?><a class="link" href="<?= h(url('/pages/client_view.php?id='.$e['entity_id'])) ?>">#<?= (int)$e['entity_id'] ?></a><?php else:?>#<?= (int)$e['entity_id'] ?><?php endif;?></td></tr><?php endforeach;?>
<?php if(!$events):?><tr><td colspan="6"><div class="empty-state">Aucune action trouvée.</div></td></tr><?php endif;?></tbody></table></div></section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
