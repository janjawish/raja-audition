<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/cosium_parser.php';

$source = $argv[1] ?? '';
if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "PDF de test manquant.\n");
    exit(1);
}

$result = extract_cosium_pdf($source);
$data = $result['parsed'];
$checks = [
    'OCR local utilisé' => $result['method'] === 'OCR local',
    'nom détecté' => $data['nom'] !== '',
    'prénom détecté' => $data['prenom'] !== '',
    'numéro Cosium détecté' => $data['cosium_numero'] !== null,
    'date de création détectée' => $data['date_creation_cosium'] === '2025-03-07',
    'date de naissance détectée' => $data['date_naissance'] === '2008-03-14',
    'caisse détectée' => str_contains((string) $data['caisse_secu'], 'CPAM'),
    'complémentaire détectée' => str_contains((string) $data['complementaire'], 'CSS'),
    'dossier Cosium détecté' => count($data['dossiers']) >= 1,
    'type de dossier détecté' => ($data['dossiers'][0]['type'] ?? '') === 'LUNETTES',
    'date du dossier détectée' => ($data['dossiers'][0]['date'] ?? null) === '2025-03-07',
];

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
if ($failed) {
    fwrite(STDERR, "ÉCHEC LECTURE COSIUM\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo 'OK — ' . count($checks) . " contrôles PDF/OCR Cosium validés.\n";
