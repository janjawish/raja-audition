<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/pages/problematiques.php');
verify_csrf();
$pdo=db();$id=(int)($_POST['id']??0);$dossier=fetch_dossier($pdo,$id);
if($dossier['statut']!=='problematique'){flash('error','Ce dossier n’est pas problématique.');redirect('/pages/dossier_view.php?id='.$id);}
$temp=$dossier;$temp['statut']=$dossier['previous_statut']?:'dossier_en_cours';$target=deadline_status($temp);if($target==='problematique')$target='dossier_en_cours';
$detail='Problème résolu : '.($dossier['probleme_type']?:'non précisé');
$stmt=$pdo->prepare('UPDATE dossiers SET statut=?,previous_statut=NULL,probleme_type=NULL,probleme_commentaire=NULL,probleme_action=NULL,updated_at=NOW() WHERE id=?');$stmt->execute([$target,$id]);
log_action($pdo,'dossier',$id,'probleme_resolu',$detail.'. Nouveau statut : '.status_label($target));flash('success','Le problème est résolu. Le dossier a retrouvé le statut adapté à son échéance.');redirect('/pages/dossier_view.php?id='.$id);
