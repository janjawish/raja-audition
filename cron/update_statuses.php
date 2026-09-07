<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';

try {
    $count = sync_automatic_statuses(db(), null);
    echo '[' . date('Y-m-d H:i:s') . '] Raja : ' . $count . " statut(s) mis à jour.\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] Erreur Raja : ' . $exception->getMessage() . "\n");
    exit(1);
}
