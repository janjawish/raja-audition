<?php

declare(strict_types=1);

function day_diff(string|DateTimeInterface $from, string|DateTimeInterface $to): int
{
    $start = $from instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($from) : new DateTimeImmutable($from);
    $end = $to instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($to) : new DateTimeImmutable($to);
    $start = $start->setTime(0, 0);
    $end = $end->setTime(0, 0);
    return (int) $start->diff($end)->format('%r%a');
}

function delay_metrics(array $dossier, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('today');
    if (empty($dossier['date_depart_delai'])) {
        return [
            'active' => false,
            'elapsed' => 0,
            'pause_days' => (int) ($dossier['jours_pause_sav'] ?? 0),
            'remaining' => null,
            'card_date' => null,
            'invoice_date' => null,
        ];
    }

    $start = new DateTimeImmutable($dossier['date_depart_delai']);
    $pauseDays = (int) ($dossier['jours_pause_sav'] ?? 0);
    if (!empty($dossier['sav_actif']) && !empty($dossier['date_debut_sav'])) {
        $pauseDays += max(0, day_diff($dossier['date_debut_sav'], $now));
    }

    $calendarDays = max(0, day_diff($start, $now));
    $elapsed = max(0, $calendarDays - $pauseDays);
    $cardDate = $start->modify('+' . (23 + $pauseDays) . ' days');
    $invoiceDate = $start->modify('+' . (30 + $pauseDays) . ' days');

    return [
        'active' => true,
        'elapsed' => $elapsed,
        'pause_days' => $pauseDays,
        'remaining' => max(0, 30 - $elapsed),
        'raw_remaining' => 30 - $elapsed,
        'card_date' => $cardDate->format('Y-m-d'),
        'invoice_date' => $invoiceDate->format('Y-m-d'),
    ];
}

function deadline_status(array $dossier, ?DateTimeImmutable $now = null): string
{
    $current = $dossier['statut'] ?? 'dossier_en_cours';
    if (in_array($current, ['facture', 'annule', 'attente_stock', 'sav', 'problematique'], true)) {
        return $current;
    }
    $metrics = delay_metrics($dossier, $now);
    if (!$metrics['active']) {
        return $current;
    }
    $today = ($now ?? new DateTimeImmutable('today'))->format('Y-m-d');
    if ($metrics['invoice_date'] <= $today) {
        return 'a_facturer';
    }
    if ($metrics['card_date'] <= $today) {
        return 'carte_vitale_a_recuperer';
    }
    return 'dossier_en_cours';
}

function recalculate_deadline_dates(PDO $pdo, int $dossierId): array
{
    $stmt = $pdo->prepare('SELECT * FROM dossiers WHERE id = ?');
    $stmt->execute([$dossierId]);
    $dossier = $stmt->fetch();
    if (!$dossier) {
        throw new RuntimeException('Dossier introuvable.');
    }
    $metrics = delay_metrics($dossier);
    $update = $pdo->prepare('UPDATE dossiers SET date_prevue_carte_vitale = ?, date_prevue_facturation = ?, updated_at = NOW() WHERE id = ?');
    $update->execute([$metrics['card_date'], $metrics['invoice_date'], $dossierId]);
    return $metrics;
}
