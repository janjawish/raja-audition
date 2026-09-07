<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/pages/dossiers.php');
verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$clientId = (int) ($_POST['client_id'] ?? 0);
$stock = (int) ($_POST['appareil_en_stock'] ?? 0) === 1 ? 1 : 0;
$dateDepart = nullable_date(post_string('date_depart_delai'));
$facturation = post_string('facturation_status') === 'oui' ? 'oui' : 'non';
if (!$clientId) { flash('error','Sélectionnez un client.'); redirect('/pages/dossier_form.php' . ($id ? '?id='.$id : '')); }
if ($stock && !$dateDepart && $facturation === 'non') { flash('error','Indiquez la date de départ du délai pour un appareil en stock.'); redirect('/pages/dossier_form.php' . ($id ? '?id='.$id : '?client_id='.$clientId)); }

$status = $stock ? 'dossier_en_cours' : 'attente_stock';
if ($facturation === 'oui') $status = 'facture';
$pauseDays = 0;
$cardDate = $dateDepart ? (new DateTimeImmutable($dateDepart))->modify('+23 days')->format('Y-m-d') : null;
$invoiceDate = $dateDepart ? (new DateTimeImmutable($dateDepart))->modify('+30 days')->format('Y-m-d') : null;
if ($facturation === 'non' && $stock && $invoiceDate && $invoiceDate <= date('Y-m-d')) $status='a_facturer';
elseif ($facturation === 'non' && $stock && $cardDate && $cardDate <= date('Y-m-d')) $status='carte_vitale_a_recuperer';

$data = [
 $clientId, in_array(post_string('ordo_status'),['oui','non','attente'],true)?post_string('ordo_status'):'attente', nullable_date(post_string('date_ordo')), post_string('mutuelle_css'), nullable_date(post_string('date_appareillage')), max(0,(float)str_replace(',','.',post_string('total_ttc','0')??'0')), (int)($_POST['pec_faite']??0)===1?1:0, nullable_date(post_string('pec_faite_le')), nullable_date(post_string('facturer_a_partir_de')), $facturation, nullable_date(post_string('facturation_faite_le')), post_string('commentaire'), $status, $stock, nullable_date(post_string('date_arrivee_stock')), $stock?$dateDepart:null, $stock?$cardDate:null, $stock?$invoiceDate:null, in_array(post_string('priority'),['basse','normale','haute','urgente'],true)?post_string('priority'):'normale'
];
$pdo=db(); $pdo->beginTransaction();
try {
 if($id){
   $old=fetch_dossier($pdo,$id);
   // Preserve active SAV/problem workflows when editing ordinary fields.
   if(in_array($old['statut'],['sav','problematique'],true) && $facturation==='non') $data[12]=$old['statut'];
   $stmt=$pdo->prepare('UPDATE dossiers SET client_id=?,ordo_status=?,date_ordo=?,mutuelle_css=?,date_appareillage=?,total_ttc=?,pec_faite=?,pec_faite_le=?,facturer_a_partir_de=?,facturation_status=?,facturation_faite_le=?,commentaire=?,statut=?,appareil_en_stock=?,date_arrivee_stock=?,date_depart_delai=?,date_prevue_carte_vitale=?,date_prevue_facturation=?,priority=?,updated_at=NOW() WHERE id=?');
   $stmt->execute([...$data,$id]);
   log_action($pdo,'dossier',$id,'modification_dossier','Informations du dossier mises à jour');
   flash('success','Le dossier a été mis à jour.');
 } else {
   $stmt=$pdo->prepare('INSERT INTO dossiers (client_id,ordo_status,date_ordo,mutuelle_css,date_appareillage,total_ttc,pec_faite,pec_faite_le,facturer_a_partir_de,facturation_status,facturation_faite_le,commentaire,statut,appareil_en_stock,date_arrivee_stock,date_depart_delai,date_prevue_carte_vitale,date_prevue_facturation,priority,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
   $stmt->execute([...$data,(int)current_user()['id']]); $id=(int)$pdo->lastInsertId();
   log_action($pdo,'dossier',$id,'creation_dossier','Dossier créé en statut '.status_label($status));
   flash('success','Le dossier audition a été créé.');
 }
 $pdo->commit();
} catch(Throwable $e){$pdo->rollBack(); throw $e;}
redirect('/pages/dossier_view.php?id='.$id);
