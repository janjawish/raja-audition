<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';require_auth();

function import_normalize(string $value): string {
    $value=mb_strtolower(trim($value));$converted=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);$value=$converted!==false?$converted:$value;return preg_replace('/[^a-z0-9]+/','',$value)??'';
}
function import_bool(?string $value): int {return in_array(import_normalize((string)$value),['oui','o','yes','1','x','vrai','faite','fait'],true)?1:0;}
function import_date_value(?string $value,array &$warnings,string $field): ?string {
    $value=trim((string)$value);if($value==='')return null;
    if(str_contains(mb_strtoupper($value),'#VALUE')||str_contains(mb_strtoupper($value),'#VALEUR')){$warnings[]="$field : erreur de formule ($value)";return null;}
    if(is_numeric($value)&&((float)$value)>1000){try{return (new DateTimeImmutable('1899-12-30'))->modify('+'.((int)$value).' days')->format('Y-m-d');}catch(Throwable){}}
    foreach(['!d/m/Y','!d-m-Y','!Y-m-d','!d.m.Y'] as $format){$d=DateTimeImmutable::createFromFormat($format,$value);if($d&&$d->format(str_replace('!','',$format))===$value)return $d->format('Y-m-d');}
    if(preg_match('/^(\d{1,2})[\/\-.](\d{1,2})$/',$value,$m)){$year=(int)date('Y');$d=DateTimeImmutable::createFromFormat('!j/n/Y',$m[1].'/'.$m[2].'/'.$year);if($d){$warnings[]="$field : année absente, $year proposé";return $d->format('Y-m-d');}}
    $warnings[]="$field : date illisible ($value)";return null;
}
function csv_cell(array $row,array $map,string $key): string {if(!isset($map[$key])||$map[$key]==='')return '';return trim((string)($row[(int)$map[$key]]??''));}

if(isset($_GET['cancel'])){unset($_SESSION['csv_import']);redirect('/pages/import_excel.php');}
if($_SERVER['REQUEST_METHOD']!=='POST')redirect('/pages/import_excel.php');verify_csrf();$stage=post_string('stage','preview');
if($stage==='preview'){
    $file=$_FILES['csv_file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK){flash('error','Le fichier n’a pas pu être reçu.');redirect('/pages/import_excel.php');}
    if($file['size']>5*1024*1024){flash('error','Le fichier dépasse la limite de 5 Mo.');redirect('/pages/import_excel.php');}
    $extension=mb_strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));if($extension!=='csv'){flash('error','En V1, importez un fichier CSV exporté depuis Excel.');redirect('/pages/import_excel.php');}
    $raw=file_get_contents($file['tmp_name']);if($raw===false){flash('error','Impossible de lire le fichier.');redirect('/pages/import_excel.php');}
    $raw=preg_replace('/^\xEF\xBB\xBF/','',$raw);if(!mb_check_encoding($raw,'UTF-8'))$raw=mb_convert_encoding($raw,'UTF-8','Windows-1252');
    $first=strtok($raw,"\r\n")?:'';$separators=[';'=>substr_count($first,';'),','=>substr_count($first,','),"\t"=>substr_count($first,"\t")];arsort($separators);$delimiter=(string)array_key_first($separators);
    $stream=fopen('php://temp','r+');fwrite($stream,$raw);rewind($stream);$rows=[];$headers=fgetcsv($stream,0,$delimiter, '"', '\\')?:[];while(($row=fgetcsv($stream,0,$delimiter, '"', '\\'))!==false&&count($rows)<2000){if(count(array_filter($row,fn($v)=>trim((string)$v)!==''))===0)continue;$rows[]=$row;}fclose($stream);
    if(count($headers)<2||!$rows){flash('error','Aucune donnée exploitable détectée dans ce CSV.');redirect('/pages/import_excel.php');}
    $aliases=['nom'=>['nom','client','patient'],'prenom'=>['prenom'],'ordo'=>['ordo','ordonnance','ordoouinon'],'date_ordo'=>['dateordo','dateordonnance'],'mutuelle'=>['mutuellecss','mutuelle','css'],'date_appareillage'=>['dateappareillage','appareillage'],'total'=>['totalttc','total','totale'],'pec_faite'=>['pecfaite','pec'],'pec_date'=>['pecfaitele','datepec'],'facturer_date'=>['facturerapartirde','facturerapartir','datefacturation'],'facturation'=>['facturationouinon','facturation','facture'],'facturation_date'=>['facturationfaitele','facturele'],'commentaire'=>['commentaire','commentaires','notes']];
    $normalized=array_map(fn($h)=>import_normalize((string)$h),$headers);$auto=[];foreach($aliases as $target=>$names){foreach($normalized as $idx=>$head){if(in_array($head,$names,true)){$auto[$target]=$idx;break;}}}
    $_SESSION['csv_import']=['file_name'=>basename($file['name']),'headers'=>$headers,'rows'=>$rows,'auto_map'=>$auto];redirect('/pages/import_excel.php');
}

if($stage==='commit'){
    $preview=$_SESSION['csv_import']??null;$map=$_POST['map']??[];if(!$preview||!is_array($map)){flash('error','La prévisualisation a expiré. Recommencez l’import.');redirect('/pages/import_excel.php');}
    if(!isset($map['nom'],$map['prenom'])||$map['nom']===''||$map['prenom']===''){flash('error','Mappez obligatoirement les colonnes NOM et Prénom.');redirect('/pages/import_excel.php');}
    $pdo=db();$success=0;$errors=0;$report=[];
    foreach($preview['rows'] as $line=>$row){$lineNo=$line+2;$warnings=[];try{
        $nom=mb_strtoupper(csv_cell($row,$map,'nom'));$prenom=csv_cell($row,$map,'prenom');if($nom===''||$prenom==='')throw new RuntimeException('nom ou prénom manquant');
        $clientStmt=$pdo->prepare('SELECT id FROM clients WHERE UPPER(nom)=? AND LOWER(prenom)=LOWER(?) ORDER BY id LIMIT 1');$clientStmt->execute([$nom,$prenom]);$clientId=(int)($clientStmt->fetchColumn()?:0);
        $pdo->beginTransaction();if(!$clientId){$s=$pdo->prepare('INSERT INTO clients(nom,prenom,notes) VALUES(?,?,?)');$s->execute([$nom,$prenom,'Créé par import CSV']);$clientId=(int)$pdo->lastInsertId();log_action($pdo,'client',$clientId,'creation_client_import',$nom.' '.$prenom);}
        $dateOrdo=import_date_value(csv_cell($row,$map,'date_ordo'),$warnings,'date ordonnance');$app=import_date_value(csv_cell($row,$map,'date_appareillage'),$warnings,'date appareillage');$pecDate=import_date_value(csv_cell($row,$map,'pec_date'),$warnings,'date PEC');$facturer=import_date_value(csv_cell($row,$map,'facturer_date'),$warnings,'date facturer à partir de');$factDate=import_date_value(csv_cell($row,$map,'facturation_date'),$warnings,'date facturation');
        $facture=import_bool(csv_cell($row,$map,'facturation'));$comment=csv_cell($row,$map,'commentaire');$problem=preg_match('/\b(pb|radie|radié|bloque|bloqué|plus de nouvelles|instance|carte vitale|pec perimee|pec périmée)\b/iu',$comment)===1;
        $start=$app;$card=$start?(new DateTimeImmutable($start))->modify('+23 days')->format('Y-m-d'):null;$invoice=$start?(new DateTimeImmutable($start))->modify('+30 days')->format('Y-m-d'):null;
        if($facture)$status='facture';elseif($problem){$status='problematique';$warnings[]='commentaire à vérifier : dossier marqué problématique';}elseif($facturer&&$facturer<=date('Y-m-d'))$status='a_facturer';elseif($invoice&&$invoice<=date('Y-m-d'))$status='a_facturer';elseif($card&&$card<=date('Y-m-d'))$status='carte_vitale_a_recuperer';elseif(!$start)$status='attente_stock';else$status='dossier_en_cours';
        $total=preg_replace('/[^0-9,.-]/','',csv_cell($row,$map,'total'));$total=(float)str_replace(',','.',(string)$total);$ordoRaw=import_normalize(csv_cell($row,$map,'ordo'));$ordo=in_array($ordoRaw,['oui','o','1','yes'],true)?'oui':(in_array($ordoRaw,['non','n','0','no'],true)?'non':'attente');
        $s=$pdo->prepare('INSERT INTO dossiers(client_id,ordo_status,date_ordo,mutuelle_css,date_appareillage,total_ttc,pec_faite,pec_faite_le,facturer_a_partir_de,facturation_status,facturation_faite_le,commentaire,statut,previous_statut,appareil_en_stock,date_depart_delai,date_prevue_carte_vitale,date_prevue_facturation,date_carte_vitale_demandee,probleme_type,probleme_commentaire,priority,import_warning,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $previous=$problem?($start?'dossier_en_cours':'attente_stock'):null;$problemType=$problem?'autre':null;$s->execute([$clientId,$ordo,$dateOrdo,csv_cell($row,$map,'mutuelle'),$app,max(0,$total),import_bool(csv_cell($row,$map,'pec_faite')),$pecDate,$facturer,$facture?'oui':'non',$factDate,$comment,$status,$previous,$start?1:0,$start,$card,$invoice,$status==='carte_vitale_a_recuperer'?date('Y-m-d'):null,$problemType,$problem?$comment:null,$problem?'haute':'normale',implode(' | ',$warnings)?:null,(int)current_user()['id']]);$dossierId=(int)$pdo->lastInsertId();log_action($pdo,'dossier',$dossierId,'creation_dossier_import','Ligne '.$lineNo.' de '.$preview['file_name']);$pdo->commit();$success++;if($warnings){$errors++;$report[]=['line'=>$lineNo,'level'=>'warning','message'=>implode(' | ',$warnings)];}
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$errors++;$report[]=['line'=>$lineNo,'level'=>'error','message'=>$e->getMessage()];}}
    $s=$pdo->prepare('INSERT INTO imports(file_name,import_type,total_rows,success_rows,error_rows,report,created_by) VALUES(?,?,?,?,?,?,?)');$s->execute([$preview['file_name'],'csv',count($preview['rows']),$success,$errors,json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),(int)current_user()['id']]);$importId=(int)$pdo->lastInsertId();log_action($pdo,'import',$importId,'import_csv',$success.' dossier(s) importé(s), '.$errors.' alerte(s)/erreur(s)');unset($_SESSION['csv_import']);flash($errors?'warning':'success',$success.' dossier(s) importé(s). '.$errors.' alerte(s) ou erreur(s) consignées dans le rapport.');redirect('/pages/import_excel.php');
}
redirect('/pages/import_excel.php');
