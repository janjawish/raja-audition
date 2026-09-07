<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/cosium_parser.php';
require_auth();

if (isset($_GET['cancel'])) { unset($_SESSION['cosium_preview']); redirect('/pages/import_cosium.php'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/pages/import_cosium.php');
verify_csrf();
$stage = post_string('stage', 'preview');

if ($stage === 'preview') {
    $file = $_FILES['cosium_pdf'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 10 * 1024 * 1024) {
        flash('error', 'Le PDF n’a pas pu être reçu ou dépasse 10 Mo.'); redirect('/pages/import_cosium.php');
    }
    $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 5);
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf' || $head !== '%PDF-') {
        flash('error', 'Le fichier sélectionné n’est pas un PDF valide.'); redirect('/pages/import_cosium.php');
    }
    try {
        $result = extract_cosium_pdf($file['tmp_name']);
        $parsed = $result['parsed'];
        $existing = null;
        if ($parsed['cosium_numero']) {
            $stmt = db()->prepare('SELECT id, nom, prenom FROM clients WHERE cosium_numero = ? LIMIT 1');
            $stmt->execute([$parsed['cosium_numero']]); $existing = $stmt->fetch() ?: null;
        }
        if (!$existing && $parsed['nom'] && $parsed['prenom']) {
            $stmt = db()->prepare('SELECT id, nom, prenom FROM clients WHERE UPPER(nom)=? AND LOWER(prenom)=LOWER(?) LIMIT 1');
            $stmt->execute([mb_strtoupper($parsed['nom']), $parsed['prenom']]); $existing = $stmt->fetch() ?: null;
        }
        $_SESSION['cosium_preview'] = ['file_name'=>basename($file['name']),'file_hash'=>hash_file('sha256',$file['tmp_name']),'method'=>$result['method'],'parsed'=>$parsed,'existing_client'=>$existing];
        flash('success', 'Le PDF a été lu. Vérifiez les informations avant de créer la fiche.');
    } catch (Throwable $e) { flash('error', $e->getMessage()); }
    redirect('/pages/import_cosium.php');
}

if ($stage === 'commit') {
    $preview = $_SESSION['cosium_preview'] ?? null;
    if (!$preview) { flash('error','La prévisualisation a expiré.'); redirect('/pages/import_cosium.php'); }
    $nom=mb_strtoupper(post_string('nom','')??'');$prenom=post_string('prenom','')??'';
    if($nom===''||$prenom===''){flash('error','Le nom et le prénom sont obligatoires.');redirect('/pages/import_cosium.php');}
    $email=post_string('email');if($email&&!filter_var($email,FILTER_VALIDATE_EMAIL)){flash('error','L’adresse e-mail n’est pas valide.');redirect('/pages/import_cosium.php');}
    $existingId=(int)($_POST['existing_client_id']??0);$pdo=db();$pdo->beginTransaction();
    try {
        $values=[post_string('telephone'),$email,post_string('notes'),post_string('cosium_numero')?:null,nullable_date(post_string('date_naissance')),post_string('adresse'),post_string('caisse_secu'),post_string('numero_securite_sociale'),post_string('complementaire'),post_string('assure_nom'),nullable_date(post_string('date_creation_cosium'))];
        if($existingId){$stmt=$pdo->prepare('UPDATE clients SET nom=?,prenom=?,telephone=?,email=?,notes=?,cosium_numero=?,date_naissance=?,adresse=?,caisse_secu=?,numero_securite_sociale=?,complementaire=?,assure_nom=?,date_creation_cosium=?,source_cosium_imported_at=NOW(),updated_at=NOW() WHERE id=?');$stmt->execute([$nom,$prenom,...$values,$existingId]);$clientId=$existingId;$action='mise_a_jour_client_cosium';}
        else{$stmt=$pdo->prepare('INSERT INTO clients(nom,prenom,telephone,email,notes,cosium_numero,date_naissance,adresse,caisse_secu,numero_securite_sociale,complementaire,assure_nom,date_creation_cosium,source_cosium_imported_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');$stmt->execute([$nom,$prenom,...$values]);$clientId=(int)$pdo->lastInsertId();$action='creation_client_cosium';}
        $createdAudition=0;
        foreach($preview['parsed']['dossiers'] as $record){
            $stmt=$pdo->prepare('INSERT INTO cosium_dossiers(client_id,dossier_type,dossier_date,details,source_reference) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE details=VALUES(details),source_reference=VALUES(source_reference),id=LAST_INSERT_ID(id)');$stmt->execute([$clientId,$record['type'],$record['date'],$record['details'],$preview['file_name']]);$cosiumId=(int)$pdo->lastInsertId();
            if(isset($_POST['create_audition_dossiers'])&&str_contains($record['type'],'AUDITION')){$check=$pdo->prepare('SELECT raja_dossier_id FROM cosium_dossiers WHERE id=?');$check->execute([$cosiumId]);if(!$check->fetchColumn()){$d=$pdo->prepare("INSERT INTO dossiers(client_id,ordo_status,date_appareillage,commentaire,statut,appareil_en_stock,priority,created_by) VALUES(?,'attente',?,?,'attente_stock',0,'normale',?)");$d->execute([$clientId,$record['date'],'Importé depuis le PDF Cosium : '.$record['details'],current_user()['id']]);$rajaId=(int)$pdo->lastInsertId();$pdo->prepare('UPDATE cosium_dossiers SET raja_dossier_id=? WHERE id=?')->execute([$rajaId,$cosiumId]);log_action($pdo,'dossier',$rajaId,'creation_dossier_cosium','Dossier audition Cosium importé en attente stock');$createdAudition++;}}
        }
        log_action($pdo,'client',$clientId,$action,'PDF Cosium lu par '.$preview['method'].' · '.count($preview['parsed']['dossiers']).' dossier(s) détecté(s)');
        $report=json_encode(['sha256'=>$preview['file_hash'],'method'=>$preview['method'],'cosium_dossiers'=>count($preview['parsed']['dossiers']),'raja_dossiers'=>$createdAudition],JSON_UNESCAPED_UNICODE);
        $stmt=$pdo->prepare("INSERT INTO imports(file_name,import_type,total_rows,success_rows,error_rows,report,created_by) VALUES(?,'pdf_cosium',?,1,0,?,?)");$stmt->execute([$preview['file_name'],count($preview['parsed']['dossiers']),$report,current_user()['id']]);
        $pdo->commit();unset($_SESSION['cosium_preview']);flash('success','La fiche Cosium a été enregistrée avec '.count($preview['parsed']['dossiers']).' dossier(s) associé(s).');redirect('/pages/client_view.php?id='.$clientId);
    } catch(Throwable $e){$pdo->rollBack();flash('error','La fiche n’a pas pu être enregistrée : '.$e->getMessage());redirect('/pages/import_cosium.php');}
}
redirect('/pages/import_cosium.php');
