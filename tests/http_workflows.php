<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

$base = 'http://localhost/raja';
$cookie = tempnam(sys_get_temp_dir(), 'raja_cookie_');
$csvPath = tempnam(sys_get_temp_dir(), 'raja_import_') . '.csv';
$tokenSuffix = date('YmdHis');
$testNom = '__RAJA_TEST_' . $tokenSuffix;
$testEmail = 'workflow.' . $tokenSuffix . '@raja.local';
$adminEmail = mb_strtolower(trim((string) getenv('RAJA_TEST_ADMIN_EMAIL')));
$adminPassword = (string) getenv('RAJA_TEST_ADMIN_PASSWORD');
$employeeEmail = mb_strtolower(trim((string) getenv('RAJA_TEST_EMPLOYEE_EMAIL')));
$employeePassword = (string) getenv('RAJA_TEST_EMPLOYEE_PASSWORD');
$patronEmail = mb_strtolower(trim((string) getenv('RAJA_TEST_OWNER_EMAIL')));
$patronPassword = (string) getenv('RAJA_TEST_OWNER_PASSWORD');
$cosiumPdf = (string) getenv('RAJA_TEST_COSIUM_PDF');
$failures = [];
$createdClientId = 0;
$createdDossierId = 0;
$createdUserId = 0;
$pdo = db();
$originalSettings = [];
foreach ($pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('shop_name','stock_warning_days')") as $row) {
    $originalSettings[$row['setting_key']] = $row['setting_value'];
}

function http_call(string $url, string $cookie, string $method = 'GET', array $data = [], bool $multipart = false): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_TIMEOUT => 45,
    ]);
    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $multipart ? $data : http_build_query($data));
    }
    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $effective = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);
    if ($body === false) throw new RuntimeException('Erreur HTTP : ' . $error);
    return compact('status', 'effective', 'body');
}

function csrf_from(string $body): string
{
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $body, $match)) {
        throw new RuntimeException('Jeton CSRF introuvable.');
    }
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}

function check_test(bool $ok, string $message, array &$failures): void
{
    if (!$ok) $failures[] = $message;
}

function login_session(string $base, string $cookie, string $email, string $password): array
{
    $page = http_call($base . '/login.php', $cookie);
    return http_call($base . '/login.php', $cookie, 'POST', ['csrf_token'=>csrf_from($page['body']),'email'=>$email,'password'=>$password]);
}

try {
    if ($adminEmail === '' || $adminPassword === '' || $employeeEmail === '' || $employeePassword === '' || $patronEmail === '' || $patronPassword === '' || $cosiumPdf === '' || !is_file($cosiumPdf)) {
        throw new RuntimeException('Définissez les variables RAJA_TEST_ADMIN_*, RAJA_TEST_EMPLOYEE_*, RAJA_TEST_OWNER_* et RAJA_TEST_COSIUM_PDF avant ce test.');
    }
    $login = login_session($base, $cookie, $adminEmail, $adminPassword);
    check_test(str_contains($login['effective'], '/pages/dashboard.php'), 'Connexion admin', $failures);
    check_test(!preg_match('/Warning:|Undefined array key|Fatal error/i', $login['body']), 'Dashboard sans avertissement PHP', $failures);
    check_test(substr_count($login['body'], 'class="stat-card"') === 7, 'Sept cartes dashboard', $failures);

    $pages = ['dashboard.php','clients.php','client_form.php','dossiers.php','dossier_form.php','attente_stock.php','carte_vitale.php','a_facturer.php','factures.php','sav.php','problematiques.php','import_excel.php','import_cosium.php','historique.php','parametres.php','users.php'];
    foreach ($pages as $page) {
        $response = http_call($base . '/pages/' . $page, $cookie);
        check_test($response['status'] === 200 && !preg_match('/Warning:|Fatal error/i', $response['body']), 'Page ' . $page, $failures);
    }

    $form = http_call($base . '/pages/client_form.php', $cookie);
    $created = http_call($base . '/actions/client_save.php', $cookie, 'POST', [
        'csrf_token'=>csrf_from($form['body']),'id'=>0,'nom'=>$testNom,'prenom'=>'Workflow','telephone'=>'0102030405','email'=>'test@example.test','notes'=>'Test automatisé'
    ]);
    preg_match('/client_view\.php\?id=(\d+)/', $created['effective'], $match);
    $createdClientId = (int) ($match[1] ?? 0);
    check_test($createdClientId > 0, 'Création client', $failures);

    $dossierForm = http_call($base . '/pages/dossier_form.php?client_id=' . $createdClientId, $cookie);
    $dossierCreated = http_call($base . '/actions/dossier_save.php', $cookie, 'POST', [
        'csrf_token'=>csrf_from($dossierForm['body']),'id'=>0,'client_id'=>$createdClientId,'priority'=>'normale','ordo_status'=>'attente','date_appareillage'=>date('Y-m-d'),'total_ttc'=>'1900','appareil_en_stock'=>0,'pec_faite'=>0,'facturation_status'=>'non','commentaire'=>'Test workflow'
    ]);
    preg_match('/dossier_view\.php\?id=(\d+)/', $dossierCreated['effective'], $match);
    $createdDossierId = (int) ($match[1] ?? 0);
    check_test($createdDossierId > 0, 'Création dossier attente stock', $failures);
    check_test($pdo->query('SELECT statut FROM dossiers WHERE id=' . $createdDossierId)->fetchColumn() === 'attente_stock', 'Statut attente stock', $failures);

    $view = http_call($base . '/pages/dossier_view.php?id=' . $createdDossierId, $cookie);
    $csrf = csrf_from($view['body']);
    http_call($base . '/actions/status_update.php', $cookie, 'POST', ['csrf_token'=>$csrf,'id'=>$createdDossierId,'action'=>'start_delay']);
    $row=$pdo->query('SELECT * FROM dossiers WHERE id='.$createdDossierId)->fetch();
    check_test($row['statut']==='dossier_en_cours' && day_diff($row['date_depart_delai'],$row['date_prevue_facturation'])===30, 'Démarrage délai J+30', $failures);
    check_test(day_diff($row['date_depart_delai'],$row['date_prevue_carte_vitale'])===23, 'Échéance carte J+23', $failures);

    http_call($base . '/actions/status_update.php', $cookie, 'POST', ['csrf_token'=>$csrf,'id'=>$createdDossierId,'action'=>'waiting_stock']);
    check_test($pdo->query('SELECT statut FROM dossiers WHERE id='.$createdDossierId)->fetchColumn()==='attente_stock', 'Retour attente stock', $failures);
    http_call($base . '/actions/status_update.php', $cookie, 'POST', ['csrf_token'=>$csrf,'id'=>$createdDossierId,'action'=>'start_delay']);

    $editForm=http_call($base.'/pages/dossier_form.php?id='.$createdDossierId,$cookie);
    http_call($base.'/actions/dossier_save.php',$cookie,'POST',['csrf_token'=>csrf_from($editForm['body']),'id'=>$createdDossierId,'client_id'=>$createdClientId,'priority'=>'haute','ordo_status'=>'oui','date_ordo'=>date('Y-m-d'),'date_appareillage'=>date('Y-m-d'),'total_ttc'=>'1950','appareil_en_stock'=>1,'date_depart_delai'=>date('Y-m-d'),'pec_faite'=>1,'pec_faite_le'=>date('Y-m-d'),'facturation_status'=>'non','commentaire'=>'Dossier modifié']);
    check_test((float)$pdo->query('SELECT total_ttc FROM dossiers WHERE id='.$createdDossierId)->fetchColumn()===1950.0,'Modification dossier',$failures);

    $pdo->prepare("UPDATE dossiers SET statut='carte_vitale_a_recuperer' WHERE id=?")->execute([$createdDossierId]);
    http_call($base.'/actions/status_update.php',$cookie,'POST',['csrf_token'=>$csrf,'id'=>$createdDossierId,'action'=>'card_collected']);
    $row=$pdo->query('SELECT * FROM dossiers WHERE id='.$createdDossierId)->fetch();
    check_test($row['statut']==='a_facturer' && $row['date_carte_vitale_recuperee']!==null,'Carte vitale récupérée',$failures);

    http_call($base.'/actions/sav_start.php',$cookie,'POST',['csrf_token'=>$csrf,'id'=>$createdDossierId]);
    check_test($pdo->query('SELECT statut FROM dossiers WHERE id='.$createdDossierId)->fetchColumn()==='sav','Entrée SAV',$failures);
    http_call($base.'/actions/sav_end.php',$cookie,'POST',['csrf_token'=>$csrf,'id'=>$createdDossierId]);
    check_test((int)$pdo->query('SELECT sav_actif FROM dossiers WHERE id='.$createdDossierId)->fetchColumn()===0,'Sortie SAV',$failures);

    http_call($base.'/actions/problem_save.php',$cookie,'POST',['csrf_token'=>$csrf,'id'=>$createdDossierId,'probleme_type'=>'autre','probleme_commentaire'=>'Test problème','probleme_action'=>'Vérifier']);
    check_test($pdo->query('SELECT statut FROM dossiers WHERE id='.$createdDossierId)->fetchColumn()==='problematique','Ajout problème',$failures);
    http_call($base.'/actions/problem_resolve.php',$cookie,'POST',['csrf_token'=>$csrf,'id'=>$createdDossierId]);
    check_test($pdo->query('SELECT probleme_type FROM dossiers WHERE id='.$createdDossierId)->fetchColumn()===false || $pdo->query('SELECT probleme_type FROM dossiers WHERE id='.$createdDossierId)->fetchColumn()===null,'Résolution problème',$failures);

    http_call($base.'/actions/status_update.php',$cookie,'POST',['csrf_token'=>$csrf,'id'=>$createdDossierId,'action'=>'comment','commentaire'=>'Commentaire automatique']);
    check_test(str_contains((string)$pdo->query('SELECT commentaire FROM dossiers WHERE id='.$createdDossierId)->fetchColumn(),'Commentaire automatique'),'Ajout commentaire',$failures);
    http_call($base.'/actions/status_update.php',$cookie,'POST',['csrf_token'=>$csrf,'id'=>$createdDossierId,'action'=>'to_invoice']);
    http_call($base.'/actions/status_update.php',$cookie,'POST',['csrf_token'=>$csrf,'id'=>$createdDossierId,'action'=>'invoice']);
    check_test($pdo->query('SELECT statut FROM dossiers WHERE id='.$createdDossierId)->fetchColumn()==='facture','Confirmation facturation',$failures);

    $badCsrf=http_call($base.'/actions/status_update.php',$cookie,'POST',['csrf_token'=>'invalide','id'=>$createdDossierId,'action'=>'comment','commentaire'=>'Ne doit pas passer']);
    check_test($badCsrf['status']===403,'Protection CSRF (HTTP '.$badCsrf['status'].')',$failures);

    file_put_contents($csvPath,"NOM;Prénom;ORDO (Oui/Non);DATE APPAREILLAGE;FACTURATION (Oui/Non);COMMENTAIRE\n{$testNom}_IMPORT;CSV;Oui;".date('d/m/Y').";Non;Test import\n");
    $importPage=http_call($base.'/pages/import_excel.php',$cookie);
    http_call($base.'/actions/import_excel_action.php',$cookie,'POST',['csrf_token'=>csrf_from($importPage['body']),'stage'=>'preview','csv_file'=>new CURLFile($csvPath,'text/csv',basename($csvPath))],true);
    $mappingPage=http_call($base.'/pages/import_excel.php',$cookie);
    http_call($base.'/actions/import_excel_action.php',$cookie,'POST',['csrf_token'=>csrf_from($mappingPage['body']),'stage'=>'commit','map'=>['nom'=>0,'prenom'=>1,'ordo'=>2,'date_appareillage'=>3,'facturation'=>4,'commentaire'=>5]]);
    check_test((int)$pdo->query("SELECT COUNT(*) FROM clients WHERE nom LIKE '__RAJA_TEST_%_IMPORT'")->fetchColumn()===1,'Import CSV avec mapping',$failures);

    $cosiumPage=http_call($base.'/pages/import_cosium.php',$cookie);
    $cosiumPreview=http_call($base.'/actions/cosium_pdf_action.php',$cookie,'POST',['csrf_token'=>csrf_from($cosiumPage['body']),'stage'=>'preview','cosium_pdf'=>new CURLFile($cosiumPdf,'application/pdf','cosium-test.pdf')],true);
    check_test(str_contains($cosiumPreview['effective'],'/pages/import_cosium.php') && str_contains($cosiumPreview['body'],'OCR local'),'Prévisualisation PDF Cosium OCR',$failures);
    http_call($base.'/actions/cosium_pdf_action.php?cancel=1',$cookie);

    $usersPage=http_call($base.'/pages/users.php',$cookie);
    $testPassword = bin2hex(random_bytes(16)) . 'aA!';
    http_call($base.'/actions/user_save.php',$cookie,'POST',['csrf_token'=>csrf_from($usersPage['body']),'id'=>0,'name'=>'Compte test','email'=>$testEmail,'role'=>'employe','is_active'=>1,'password'=>$testPassword]);
    $createdUserId=(int)$pdo->query("SELECT id FROM users WHERE email=".$pdo->quote($testEmail))->fetchColumn();
    check_test($createdUserId>0 && password_verify($testPassword,(string)$pdo->query('SELECT password_hash FROM users WHERE id='.$createdUserId)->fetchColumn()),'Gestion utilisateurs et mot de passe haché',$failures);

    $settingsPage=http_call($base.'/pages/parametres.php',$cookie);
    http_call($base.'/actions/settings_save.php',$cookie,'POST',['csrf_token'=>csrf_from($settingsPage['body']),'shop_name'=>'Raja Test Automatique','stock_warning_days'=>17]);
    check_test($pdo->query("SELECT setting_value FROM settings WHERE setting_key='stock_warning_days'")->fetchColumn()==='17','Enregistrement paramètres',$failures);

    $employeeCookie=tempnam(sys_get_temp_dir(),'raja_emp_');login_session($base,$employeeCookie,$employeeEmail,$employeePassword);
    check_test(http_call($base.'/pages/users.php',$employeeCookie)['status']===403,'Restriction utilisateurs pour employé',$failures);
    check_test(http_call($base.'/pages/parametres.php',$employeeCookie)['status']===403,'Restriction paramètres pour employé',$failures);
    @unlink($employeeCookie);
    $patronCookie=tempnam(sys_get_temp_dir(),'raja_patron_');login_session($base,$patronCookie,$patronEmail,$patronPassword);
    check_test(http_call($base.'/pages/parametres.php',$patronCookie)['status']===200,'Accès paramètres patron',$failures);
    check_test(http_call($base.'/pages/users.php',$patronCookie)['status']===403,'Restriction utilisateurs pour patron',$failures);
    @unlink($patronCookie);
} catch (Throwable $exception) {
    $failures[] = 'Exception : ' . $exception->getMessage();
} finally {
    try {
        $clientIds=$pdo->query("SELECT id FROM clients WHERE nom LIKE '__RAJA_TEST_%'")->fetchAll(PDO::FETCH_COLUMN);
        if($clientIds){$marks=implode(',',array_fill(0,count($clientIds),'?'));$s=$pdo->prepare("SELECT id FROM dossiers WHERE client_id IN ($marks)");$s->execute($clientIds);$dossierIds=$s->fetchAll(PDO::FETCH_COLUMN);if($dossierIds){$dm=implode(',',array_fill(0,count($dossierIds),'?'));$pdo->prepare("DELETE FROM history WHERE entity_type='dossier' AND entity_id IN ($dm)")->execute($dossierIds);$pdo->prepare("DELETE FROM dossiers WHERE id IN ($dm)")->execute($dossierIds);}$pdo->prepare("DELETE FROM history WHERE entity_type='client' AND entity_id IN ($marks)")->execute($clientIds);$pdo->prepare("DELETE FROM clients WHERE id IN ($marks)")->execute($clientIds);}
        $importLookup=$pdo->prepare('SELECT id FROM imports WHERE file_name=? AND import_type=\'csv\'');$importLookup->execute([basename($csvPath)]);$importIds=$importLookup->fetchAll(PDO::FETCH_COLUMN);if($importIds){$im=implode(',',array_fill(0,count($importIds),'?'));$pdo->prepare("DELETE FROM history WHERE entity_type='import' AND entity_id IN ($im)")->execute($importIds);$pdo->prepare("DELETE FROM imports WHERE id IN ($im)")->execute($importIds);}
        if($createdUserId){$pdo->prepare('DELETE FROM history WHERE user_id=? OR (entity_type=\'user\' AND entity_id=?)')->execute([$createdUserId,$createdUserId]);$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$createdUserId]);}
        $pdo->prepare("DELETE FROM history WHERE entity_type='settings' AND details LIKE 'Boutique : Raja Test Automatique%'")->execute();
        $restore=$pdo->prepare('UPDATE settings SET setting_value=? WHERE setting_key=?');foreach($originalSettings as $key=>$value)$restore->execute([$value,$key]);
    } catch(Throwable $cleanup) {$failures[]='Nettoyage test : '.$cleanup->getMessage();}
    @unlink($cookie);@unlink($csvPath);
}

if($failures){fwrite(STDERR,"ÉCHEC TESTS HTTP\n- ".implode("\n- ",$failures)."\n");exit(1);}
echo "OK — parcours HTTP, rôles, sécurité, clients, dossiers, statuts, SAV, problèmes, CSV, Cosium OCR, utilisateurs et paramètres validés.\n";
