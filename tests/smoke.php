<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$metrics = delay_metrics([
    'date_depart_delai' => '2026-01-01',
    'jours_pause_sav' => 5,
    'sav_actif' => 0,
], new DateTimeImmutable('2026-01-19'));
$assert($metrics['elapsed'] === 13, 'Les jours écoulés doivent exclure les 5 jours de pause.');
$assert($metrics['remaining'] === 17, 'Le nombre de jours restants doit être 17.');
$assert($metrics['card_date'] === '2026-01-29', 'La date carte vitale doit inclure la pause.');
$assert($metrics['invoice_date'] === '2026-02-05', 'La date de facturation doit inclure la pause.');

$cardStatus = deadline_status([
    'statut' => 'dossier_en_cours',
    'date_depart_delai' => '2026-01-01',
    'jours_pause_sav' => 0,
    'sav_actif' => 0,
], new DateTimeImmutable('2026-01-24'));
$assert($cardStatus === 'carte_vitale_a_recuperer', 'J+23 doit passer en carte vitale à récupérer.');

$invoiceStatus = deadline_status([
    'statut' => 'dossier_en_cours',
    'date_depart_delai' => '2026-01-01',
    'jours_pause_sav' => 0,
    'sav_actif' => 0,
], new DateTimeImmutable('2026-01-31'));
$assert($invoiceStatus === 'a_facturer', 'J+30 doit passer à facturer.');

$pdo = db();
$adminEmail = mb_strtolower(trim((string) getenv('RAJA_TEST_ADMIN_EMAIL')));
$adminPassword = (string) getenv('RAJA_TEST_ADMIN_PASSWORD');
$assert($adminEmail !== '' && $adminPassword !== '', 'Définissez RAJA_TEST_ADMIN_EMAIL et RAJA_TEST_ADMIN_PASSWORD avant le test.');
if ($adminEmail !== '' && $adminPassword !== '') {
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$adminEmail]);
    $hash = (string) $stmt->fetchColumn();
    $assert($hash !== '' && password_verify($adminPassword, $hash), 'Le compte administrateur de test doit être valide.');
}
$statuses = $pdo->query('SELECT DISTINCT statut FROM dossiers')->fetchAll(PDO::FETCH_COLUMN);
$assert(count(array_diff($statuses, valid_statuses())) === 0, 'Tous les statuts en base doivent être autorisés.');

if ($failures) {
    fwrite(STDERR, "ÉCHEC\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "OK — connexion SQL, authentification configurée et règles J+23/J+30 vérifiées.\n";
