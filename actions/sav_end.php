<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/pages/sav.php');
verify_csrf();
$pdo=db();$id=(int)($_POST['id']??0);$dossier=fetch_dossier($pdo,$id);
if($dossier['statut']!=='sav'||!$dossier['sav_actif']){flash('error','Ce dossier n’est pas en SAV actif.');redirect('/pages/dossier_view.php?id='.$id);}
$extra=max(0,day_diff($dossier['date_debut_sav']?:date('Y-m-d'),date('Y-m-d')));$total=(int)$dossier['jours_pause_sav']+$extra;
$temp=$dossier;$temp['statut']=$dossier['previous_statut']?:'dossier_en_cours';$temp['sav_actif']=0;$temp['jours_pause_sav']=$total;
$metrics=delay_metrics($temp);$target=deadline_status($temp);
if(in_array($target,['sav','problematique','attente_stock'],true))$target='dossier_en_cours';
$stmt=$pdo->prepare('UPDATE dossiers SET statut=?,previous_statut=NULL,sav_actif=0,date_fin_sav=CURDATE(),jours_pause_sav=?,date_prevue_carte_vitale=?,date_prevue_facturation=?,updated_at=NOW() WHERE id=?');$stmt->execute([$target,$total,$metrics['card_date'],$metrics['invoice_date'],$id]);
log_action($pdo,'dossier',$id,'sortie_sav','Sortie SAV après '.$extra.' jour(s) de pause. Nouveau statut : '.status_label($target));flash('success','Le SAV est terminé. Les échéances ont été décalées de '.$extra.' jour(s).');redirect('/pages/dossier_view.php?id='.$id);
