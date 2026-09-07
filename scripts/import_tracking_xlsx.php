<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

function source_text(mixed $value): string
{
    return trim(is_scalar($value) ? (string) $value : '');
}

function source_key(mixed $value): string
{
    $value = mb_strtolower(source_text($value));
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    return preg_replace('/[^a-z0-9]+/', '', $ascii !== false ? $ascii : $value) ?? '';
}

function source_date(mixed $value, array &$warnings, string $field): ?string
{
    $value = source_text($value);
    if ($value === '') return null;
    if (str_starts_with($value, '#')) {
        $warnings[] = $field . ' : ' . $value;
        return null;
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:T|\s)/', $value, $match)) {
        return $match[1];
    }
    foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date) return $date->format('Y-m-d');
    }
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})$/', $value, $match)) {
        $date = DateTimeImmutable::createFromFormat('!j/n/Y', $match[1] . '/' . $match[2] . '/2026');
        if ($date) {
            $warnings[] = $field . ' : année 2026 complétée';
            return $date->format('Y-m-d');
        }
    }
    $warnings[] = $field . ' illisible : ' . $value;
    return null;
}

function source_problem_type(string $text): string
{
    $key = source_key($text);
    return match (true) {
        str_contains($key, 'radie') => 'radié',
        str_contains($key, 'cartvitale'), str_contains($key, 'cartevitale') => 'carte vitale manquante',
        str_contains($key, 'plusdenouvelle'), str_contains($key, 'injoignable') => 'client injoignable',
        str_contains($key, 'pecperime') => 'PEC périmée',
        str_contains($key, 'teletrans') => 'problème télétransmission',
        str_contains($key, 'mutuelle') && str_contains($key, 'bloqu') => 'mutuelle bloquée',
        str_contains($key, 'factur'), str_contains($key, 'pb') => 'problème facturation',
        str_contains($key, 'erreur'), str_contains($key, 'value') => 'erreur dossier',
        default => 'autre',
    };
}

function prepare_tracking_rows(array $payload): array
{
    $prepared = [];
    $summary = ['source_rows'=>0,'clients'=>[],'statuses'=>array_fill_keys(valid_statuses(),0),'warnings'=>0,'colors'=>[]];
    foreach ($payload['rows'] ?? [] as $source) {
        $values = $source['values'] ?? [];
        $nom = mb_strtoupper(source_text($values[0] ?? ''));
        $prenom = source_text($values[1] ?? '');
        if ($nom === '' || $prenom === '') continue;
        $summary['source_rows']++;
        $summary['clients'][source_key($nom) . '|' . source_key($prenom)] = true;
        $warnings = [];
        $dateOrdo = source_date($values[3] ?? null, $warnings, 'date ordonnance');
        $dateApp = source_date($values[5] ?? null, $warnings, 'date appareillage');
        $pecDate = source_date($values[8] ?? null, $warnings, 'date PEC');
        $facturerDate = source_date($values[9] ?? null, $warnings, 'date facturer à partir de');
        $facturationDate = source_date($values[11] ?? null, $warnings, 'date facturation');
        $comment = source_text($values[12] ?? '');
        $invoiceValue = source_key($values[10] ?? '');
        $color = source_text($source['dominant_fill'] ?? '') ?: 'NONE';
        $summary['colors'][$color] = ($summary['colors'][$color] ?? 0) + 1;
        $isGreen = $color === 'FF00FF00';
        $attentionColor = in_array($color, ['FFFFFF00','FFFF9900','FFFFE599','FFF3F3F3'], true);
        $explicitInvoice = $invoiceValue === 'oui';
        $explicitProblem = str_contains($invoiceValue, 'pb');
        $problemWords = preg_match('/\b(pb|radi[eé]|bloqu[eé]|plus de nouvelles|injoignable|instance|carte vitale|pec p[eé]rim[eé]e|erreur|t[eé]l[eé]transmission)\b/iu', $comment) === 1;
        $start = $dateApp;
        $cardDate = $start ? (new DateTimeImmutable($start))->modify('+23 days')->format('Y-m-d') : null;
        $invoiceDate = $start ? (new DateTimeImmutable($start))->modify('+30 days')->format('Y-m-d') : null;
        $today = date('Y-m-d');
        $underlying = !$start ? 'attente_stock' : (($invoiceDate && $invoiceDate <= $today) ? 'a_facturer' : (($cardDate && $cardDate <= $today) ? 'carte_vitale_a_recuperer' : 'dossier_en_cours'));
        $problem = $explicitProblem || $problemWords || ($attentionColor && !$facturerDate && $comment !== '');
        if ($explicitInvoice || $isGreen) $status = 'facture';
        elseif ($problem) $status = 'problematique';
        elseif ($attentionColor || ($facturerDate && $facturerDate <= $today)) $status = 'a_facturer';
        else $status = $underlying;
        $summary['statuses'][$status]++;
        $summary['warnings'] += count($warnings);
        $prepared[] = [
            'source_row'=>(int)($source['source_row'] ?? 0),'nom'=>$nom,'prenom'=>$prenom,
            'ordo_status'=>source_key($values[2] ?? '')==='oui'?'oui':(source_key($values[2] ?? '')==='non'?'non':'attente'),
            'date_ordo'=>$dateOrdo,'mutuelle_css'=>source_text($values[4] ?? ''),'date_appareillage'=>$dateApp,
            'total_ttc'=>max(0,(float)str_replace(',','.',preg_replace('/[^0-9,.-]/','',source_text($values[6] ?? '0')) ?? '0')),
            'pec_faite'=>(int)(source_key($values[7] ?? '')==='1'||source_key($values[7] ?? '')==='oui'),'pec_faite_le'=>$pecDate,
            'facturer_a_partir_de'=>$facturerDate,'facturation_status'=>$status==='facture'?'oui':'non','facturation_faite_le'=>$facturationDate,
            'commentaire'=>$comment,'statut'=>$status,'previous_statut'=>$status==='problematique'?$underlying:null,
            'appareil_en_stock'=>$start?1:0,'date_depart_delai'=>$start,'date_prevue_carte_vitale'=>$cardDate,'date_prevue_facturation'=>$invoiceDate,
            'date_carte_vitale_demandee'=>$status==='carte_vitale_a_recuperer'?date('Y-m-d'):null,
            'probleme_type'=>$status==='problematique'?source_problem_type($invoiceValue.' '.$comment):null,
            'probleme_commentaire'=>$status==='problematique'?($comment!==''?$comment:'Alerte provenant du suivi Excel'):null,
            'probleme_action'=>$status==='problematique'?'Vérifier et traiter le dossier importé':null,
            'priority'=>$status==='problematique'?'urgente':($status==='a_facturer'?'haute':'normale'),
            'source_status'=>source_text($values[10] ?? ''),'source_color'=>$color,
            'import_warning'=>$warnings?implode(' | ',$warnings):null,
        ];
    }
    $summary['unique_clients'] = count($summary['clients']);
    unset($summary['clients']);
    return ['rows'=>$prepared,'summary'=>$summary];
}

$jsonPath = $argv[1] ?? '';
$replace = in_array('--replace', $argv, true);
if ($jsonPath === '' || !is_file($jsonPath)) {
    fwrite(STDERR, "Usage : php import_tracking_xlsx.php chemin/xlsx-extracted.json [--replace]\n"); exit(1);
}
$payload = json_decode((string) file_get_contents($jsonPath), true, 512, JSON_THROW_ON_ERROR);
$prepared = prepare_tracking_rows($payload);
if (!$replace) {
    echo json_encode($prepared['summary'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM history');
    $pdo->exec('DELETE FROM imports');
    $pdo->exec('DELETE FROM cosium_dossiers');
    $pdo->exec('DELETE FROM dossiers');
    $pdo->exec('DELETE FROM clients');
    $clients = [];
    $clientInsert = $pdo->prepare('INSERT INTO clients(nom,prenom,notes) VALUES(?,?,?)');
    $dossierInsert = $pdo->prepare('INSERT INTO dossiers(client_id,ordo_status,date_ordo,mutuelle_css,date_appareillage,total_ttc,pec_faite,pec_faite_le,facturer_a_partir_de,facturation_status,facturation_faite_le,commentaire,statut,previous_statut,appareil_en_stock,date_depart_delai,date_prevue_carte_vitale,date_prevue_facturation,date_carte_vitale_demandee,probleme_type,probleme_commentaire,probleme_action,priority,source_status,source_color,import_warning,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($prepared['rows'] as $row) {
        $key = source_key($row['nom']) . '|' . source_key($row['prenom']);
        if (!isset($clients[$key])) {
            $clientInsert->execute([$row['nom'],$row['prenom'],'Importé depuis le suivi audition 2026']);
            $clients[$key]=(int)$pdo->lastInsertId();
        }
        $dossierInsert->execute([$clients[$key],$row['ordo_status'],$row['date_ordo'],$row['mutuelle_css'],$row['date_appareillage'],$row['total_ttc'],$row['pec_faite'],$row['pec_faite_le'],$row['facturer_a_partir_de'],$row['facturation_status'],$row['facturation_faite_le'],$row['commentaire'],$row['statut'],$row['previous_statut'],$row['appareil_en_stock'],$row['date_depart_delai'],$row['date_prevue_carte_vitale'],$row['date_prevue_facturation'],$row['date_carte_vitale_demandee'],$row['probleme_type'],$row['probleme_commentaire'],$row['probleme_action'],$row['priority'],$row['source_status'],$row['source_color'],$row['import_warning'],1]);
        $dossierId=(int)$pdo->lastInsertId();
        log_action($pdo,'dossier',$dossierId,'creation_dossier_import','Ligne Excel '.$row['source_row'].' · statut '.status_label($row['statut']),1);
    }
    $report = $prepared['summary'];
    $report['source_file'] = basename($jsonPath);
    $stmt=$pdo->prepare("INSERT INTO imports(file_name,import_type,total_rows,success_rows,error_rows,report,created_by) VALUES(?,'xlsx_migration',?,?,?,?,1)");
    $stmt->execute([basename($jsonPath),count($prepared['rows']),count($prepared['rows']),$prepared['summary']['warnings'],json_encode($report,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)]);
    $importId=(int)$pdo->lastInsertId();
    log_action($pdo,'import',$importId,'import_xlsx_reel',count($prepared['rows']).' dossiers et '.count($clients).' clients importés',1);
    $pdo->commit();
    echo json_encode($prepared['summary'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch(Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Import annulé : '.$e->getMessage().PHP_EOL);
    exit(1);
}
