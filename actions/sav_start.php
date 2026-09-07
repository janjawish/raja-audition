<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/pages/sav.php');
verify_csrf();
$pdo=db(); $id=(int)($_POST['id']??0); $dossier=fetch_dossier($pdo,$id);
if(in_array($dossier['statut'],['facture','annule','sav'],true)){flash('error','Ce dossier ne peut pas être mis en SAV.');redirect('/pages/dossier_view.php?id='.$id);}
$stmt=$pdo->prepare("UPDATE dossiers SET previous_statut=statut,statut='sav',sav_actif=1,date_debut_sav=CURDATE(),date_fin_sav=NULL,updated_at=NOW() WHERE id=?");$stmt->execute([$id]);
log_action($pdo,'dossier',$id,'entree_sav','Délai mis en pause le '.date('d/m/Y'));flash('success','Le dossier est en SAV. Son délai est maintenant en pause.');redirect('/pages/dossier_view.php?id='.$id);
