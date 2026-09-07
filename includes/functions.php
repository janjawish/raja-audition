<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/delay_calculator.php';

function url(string $path = ''): string
{
    return rtrim((string) app_config('base_url'), '/') . '/' . ltrim($path, '/');
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function date_fr(?string $date, string $fallback = '—'): string
{
    if (!$date) {
        return $fallback;
    }
    try {
        return (new DateTimeImmutable($date))->format('d/m/Y');
    } catch (Throwable) {
        return $fallback;
    }
}

function money_fr(mixed $amount): string
{
    return number_format((float) $amount, 2, ',', ' ') . ' €';
}

function mask_social_number(?string $number): string
{
    $digits = preg_replace('/\D+/', '', (string) $number) ?? '';
    if ($digits === '') return '—';
    return str_repeat('•', max(0, mb_strlen($digits) - 4)) . mb_substr($digits, -4);
}

function status_label(string $status): string
{
    return [
        'dossier_en_cours' => 'Dossier en cours',
        'attente_stock' => 'Attente stock',
        'carte_vitale_a_recuperer' => 'Carte vitale à récupérer',
        'a_facturer' => 'À facturer',
        'facture' => 'Facturé',
        'sav' => 'SAV',
        'problematique' => 'Problématique',
        'annule' => 'Annulé',
    ][$status] ?? $status;
}

function status_class(string $status): string
{
    return 'badge badge--' . str_replace('_', '-', $status);
}

function priority_label(string $priority): string
{
    return ['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'][$priority] ?? $priority;
}

function priority_class(string $priority): string
{
    return 'priority priority--' . $priority;
}

function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = compact('type', 'message');
}

function consume_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function post_string(string $key, ?string $default = null): ?string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function nullable_date(?string $value): ?string
{
    if (!$value) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function log_action(PDO $pdo, string $entityType, int $entityId, string $action, string $details = '', ?int $userId = null): void
{
    $userId ??= isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare('INSERT INTO history (user_id, entity_type, entity_id, action, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$userId, $entityType, $entityId, $action, $details]);
}

function fetch_dossier(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare('SELECT d.*, c.nom, c.prenom, c.telephone, c.email FROM dossiers d JOIN clients c ON c.id = d.client_id WHERE d.id = ?');
    $stmt->execute([$id]);
    $dossier = $stmt->fetch();
    if (!$dossier) {
        http_response_code(404);
        exit('Dossier introuvable.');
    }
    return $dossier;
}

function valid_statuses(): array
{
    return ['dossier_en_cours', 'attente_stock', 'carte_vitale_a_recuperer', 'a_facturer', 'facture', 'sav', 'problematique', 'annule'];
}

function problem_types(): array
{
    return ['radié', 'dossier incomplet', 'carte vitale manquante', 'client injoignable', 'PEC périmée', 'problème facturation', 'problème télétransmission', 'mutuelle bloquée', 'erreur dossier', 'autre'];
}

function sync_automatic_statuses(PDO $pdo, ?int $actorId = null): int
{
    $rows = $pdo->query("SELECT * FROM dossiers WHERE date_depart_delai IS NOT NULL AND statut IN ('dossier_en_cours','carte_vitale_a_recuperer','a_facturer')")->fetchAll();
    $changed = 0;
    foreach ($rows as $row) {
        $metrics = delay_metrics($row);
        $target = deadline_status($row);
        $dateUpdate = $pdo->prepare('UPDATE dossiers SET date_prevue_carte_vitale = ?, date_prevue_facturation = ? WHERE id = ?');
        $dateUpdate->execute([$metrics['card_date'], $metrics['invoice_date'], $row['id']]);
        if ($target !== $row['statut']) {
            if ($target === 'carte_vitale_a_recuperer') {
                $stmt = $pdo->prepare('UPDATE dossiers SET statut = ?, date_carte_vitale_demandee = COALESCE(date_carte_vitale_demandee, CURDATE()), updated_at = NOW() WHERE id = ?');
            } else {
                $stmt = $pdo->prepare('UPDATE dossiers SET statut = ?, updated_at = NOW() WHERE id = ?');
            }
            $stmt->execute([$target, $row['id']]);
            log_action($pdo, 'dossier', (int) $row['id'], 'changement_statut_automatique', status_label($row['statut']) . ' → ' . status_label($target), $actorId);
            $changed++;
        }
    }
    return $changed;
}
