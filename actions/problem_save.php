<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/pages/problematiques.php');
verify_csrf();
$pdo=db();$id=(int)($_POST['id']??0);$dossier=fetch_dossier($pdo,$id);$type=post_string('probleme_type','')??'';$comment=post_string('probleme_commentaire','')??'';
if(!in_array($type,problem_types(),true)||$comment===''){flash('error','Renseignez un type et un commentaire.');redirect('/pages/dossier_view.php?id='.$id);}
$stmt=$pdo->prepare("UPDATE dossiers SET previous_statut=statut,statut='problematique',probleme_type=?,probleme_commentaire=?,probleme_action=?,updated_at=NOW() WHERE id=?");$stmt->execute([$type,$comment,post_string('probleme_action'),$id]);
log_action($pdo,'dossier',$id,'probleme_ajoute',ucfirst($type).' — '.$comment);flash('success','Le dossier apparaît maintenant dans les problématiques.');redirect('/pages/dossier_view.php?id='.$id);
