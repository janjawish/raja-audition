USE raja;

ALTER TABLE clients
    ADD COLUMN IF NOT EXISTS cosium_numero VARCHAR(50) NULL AFTER notes,
    ADD COLUMN IF NOT EXISTS date_naissance DATE NULL AFTER cosium_numero,
    ADD COLUMN IF NOT EXISTS adresse TEXT NULL AFTER date_naissance,
    ADD COLUMN IF NOT EXISTS caisse_secu VARCHAR(190) NULL AFTER adresse,
    ADD COLUMN IF NOT EXISTS numero_securite_sociale VARCHAR(30) NULL AFTER caisse_secu,
    ADD COLUMN IF NOT EXISTS complementaire VARCHAR(190) NULL AFTER numero_securite_sociale,
    ADD COLUMN IF NOT EXISTS assure_nom VARCHAR(190) NULL AFTER complementaire,
    ADD COLUMN IF NOT EXISTS date_creation_cosium DATE NULL AFTER assure_nom,
    ADD COLUMN IF NOT EXISTS source_cosium_imported_at DATETIME NULL AFTER date_creation_cosium;

SET @has_cosium_index = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'clients' AND index_name = 'uq_clients_cosium'
);
SET @add_cosium_index = IF(@has_cosium_index = 0,
    'ALTER TABLE clients ADD UNIQUE KEY uq_clients_cosium (cosium_numero)',
    'SELECT 1'
);
PREPARE stmt FROM @add_cosium_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS cosium_dossiers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    dossier_type VARCHAR(100) NOT NULL,
    dossier_date DATE NULL,
    details TEXT NULL,
    source_reference VARCHAR(255) NULL,
    raja_dossier_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cosium_dossiers_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_cosium_dossiers_raja FOREIGN KEY (raja_dossier_id) REFERENCES dossiers(id) ON DELETE SET NULL,
    UNIQUE KEY uq_cosium_dossier (client_id, dossier_type, dossier_date),
    INDEX idx_cosium_dossiers_client (client_id)
) ENGINE=InnoDB;
