<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/pages/dossiers.php');
verify_csrf();

$pdo = db();
$id = (int) ($_POST['id'] ?? 0);
$action = post_string('action', '') ?? '';
$dossier = fetch_dossier($pdo, $id);
$pdo->beginTransaction();
try {
    switch ($action) {
        case 'start_delay':
            if ($dossier['statut'] !== 'attente_stock') throw new RuntimeException('Ce dossier n’est pas en attente stock.');
            $start = date('Y-m-d');
            $card = date('Y-m-d', strtotime('+23 days'));
            $invoice = date('Y-m-d', strtotime('+30 days'));
            $stmt = $pdo->prepare("UPDATE dossiers SET statut='dossier_en_cours',appareil_en_stock=1,date_arrivee_stock=?,date_depart_delai=?,date_prevue_carte_vitale=?,date_prevue_facturation=?,updated_at=NOW() WHERE id=?");
            $stmt->execute([$start, $start, $card, $invoice, $id]);
            log_action($pdo, 'dossier', $id, 'demarrage_delai', 'Délai de 30 jours démarré le ' . date_fr($start));
            flash('success', 'Le délai de 30 jours a démarré. Les échéances ont été calculées.');
            break;
        case 'waiting_stock':
            $stmt = $pdo->prepare("UPDATE dossiers SET previous_statut=statut,statut='attente_stock',appareil_en_stock=0,date_depart_delai=NULL,date_prevue_carte_vitale=NULL,date_prevue_facturation=NULL,updated_at=NOW() WHERE id=?");
            $stmt->execute([$id]);
            log_action($pdo, 'dossier', $id, 'attente_stock', 'Dossier placé en attente de l’appareil');
            flash('success', 'Le dossier est maintenant en attente stock. Le délai est désactivé.');
            break;
        case 'card_collected':
            if ($dossier['statut'] !== 'carte_vitale_a_recuperer') throw new RuntimeException('La carte vitale n’est pas attendue pour ce dossier.');
            $stmt = $pdo->prepare("UPDATE dossiers SET statut='a_facturer',date_carte_vitale_recuperee=CURDATE(),updated_at=NOW() WHERE id=?");
            $stmt->execute([$id]);
            log_action($pdo, 'dossier', $id, 'carte_vitale_recuperee', 'Carte vitale récupérée, dossier passé à facturer');
            flash('success', 'Carte vitale enregistrée. Le dossier est prêt à facturer.');
            break;
        case 'to_invoice':
            $stmt = $pdo->prepare("UPDATE dossiers SET statut='a_facturer',updated_at=NOW() WHERE id=? AND statut NOT IN ('facture','annule','sav')");
            $stmt->execute([$id]);
            log_action($pdo, 'dossier', $id, 'passage_a_facturer', 'Passage manuel à facturer');
            flash('success', 'Le dossier a été placé dans « À facturer ».');
            break;
        case 'invoice':
            if ($dossier['statut'] !== 'a_facturer') throw new RuntimeException('Le dossier doit être à facturer.');
            $stmt = $pdo->prepare("UPDATE dossiers SET statut='facture',facturation_status='oui',facturation_faite_le=CURDATE(),updated_at=NOW() WHERE id=?");
            $stmt->execute([$id]);
            log_action($pdo, 'dossier', $id, 'facture', 'Facturation confirmée le ' . date('d/m/Y'));
            flash('success', 'Le dossier a été marqué comme facturé.');
            break;
        case 'comment':
            $comment = post_string('commentaire', '') ?? '';
            if ($comment === '') throw new RuntimeException('Le commentaire est vide.');
            $prefix = '[' . date('d/m/Y H:i') . ' · ' . current_user()['name'] . '] ';
            $stmt = $pdo->prepare("UPDATE dossiers SET commentaire=CONCAT_WS(CHAR(10),NULLIF(commentaire,''),?),updated_at=NOW() WHERE id=?");
            $stmt->execute([$prefix . $comment, $id]);
            log_action($pdo, 'dossier', $id, 'commentaire_ajoute', $comment);
            flash('success', 'Le commentaire a été ajouté.');
            break;
        default:
            throw new RuntimeException('Action inconnue.');
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', $e->getMessage());
}
redirect('/pages/dossier_view.php?id=' . $id);
