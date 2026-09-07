CREATE DATABASE IF NOT EXISTS raja CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE raja;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','patron','employe') NOT NULL DEFAULT 'employe',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(120) NOT NULL,
    prenom VARCHAR(120) NOT NULL,
    telephone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    notes TEXT NULL,
    cosium_numero VARCHAR(50) NULL,
    date_naissance DATE NULL,
    adresse TEXT NULL,
    caisse_secu VARCHAR(190) NULL,
    numero_securite_sociale VARCHAR(30) NULL,
    complementaire VARCHAR(190) NULL,
    assure_nom VARCHAR(190) NULL,
    date_creation_cosium DATE NULL,
    source_cosium_imported_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clients_name (nom, prenom),
    UNIQUE KEY uq_clients_cosium (cosium_numero)
) ENGINE=InnoDB;

CREATE TABLE dossiers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    ordo_status ENUM('oui','non','attente') NOT NULL DEFAULT 'attente',
    date_ordo DATE NULL,
    mutuelle_css VARCHAR(190) NULL,
    date_appareillage DATE NULL,
    total_ttc DECIMAL(10,2) NOT NULL DEFAULT 0,
    pec_faite TINYINT(1) NOT NULL DEFAULT 0,
    pec_faite_le DATE NULL,
    facturer_a_partir_de DATE NULL,
    facturation_status ENUM('oui','non') NOT NULL DEFAULT 'non',
    facturation_faite_le DATE NULL,
    commentaire TEXT NULL,
    statut ENUM('dossier_en_cours','attente_stock','carte_vitale_a_recuperer','a_facturer','facture','sav','problematique','annule') NOT NULL DEFAULT 'dossier_en_cours',
    previous_statut ENUM('dossier_en_cours','attente_stock','carte_vitale_a_recuperer','a_facturer','facture','sav','problematique','annule') NULL,
    appareil_en_stock TINYINT(1) NOT NULL DEFAULT 1,
    date_arrivee_stock DATE NULL,
    date_depart_delai DATE NULL,
    date_prevue_carte_vitale DATE NULL,
    date_prevue_facturation DATE NULL,
    date_carte_vitale_demandee DATE NULL,
    date_carte_vitale_recuperee DATE NULL,
    sav_actif TINYINT(1) NOT NULL DEFAULT 0,
    date_debut_sav DATE NULL,
    date_fin_sav DATE NULL,
    jours_pause_sav INT UNSIGNED NOT NULL DEFAULT 0,
    probleme_type VARCHAR(100) NULL,
    probleme_commentaire TEXT NULL,
    probleme_action VARCHAR(255) NULL,
    priority ENUM('basse','normale','haute','urgente') NOT NULL DEFAULT 'normale',
    source_status VARCHAR(120) NULL,
    source_color VARCHAR(50) NULL,
    import_warning VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,
    CONSTRAINT fk_dossiers_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_dossiers_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_dossiers_status (statut),
    INDEX idx_dossiers_deadlines (date_prevue_carte_vitale, date_prevue_facturation),
    INDEX idx_dossiers_client (client_id),
    INDEX idx_dossiers_priority (priority)
) ENGINE=InnoDB;

CREATE TABLE history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    action VARCHAR(120) NOT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_history_entity (entity_type, entity_id),
    INDEX idx_history_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    description VARCHAR(255) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE imports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    import_type VARCHAR(50) NOT NULL DEFAULT 'csv',
    total_rows INT UNSIGNED NOT NULL DEFAULT 0,
    success_rows INT UNSIGNED NOT NULL DEFAULT 0,
    error_rows INT UNSIGNED NOT NULL DEFAULT 0,
    report LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,
    CONSTRAINT fk_imports_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_imports_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE cosium_dossiers (
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

INSERT INTO settings (setting_key, setting_value, description) VALUES
('delay_card_days', '23', 'Nombre de jours avant la demande de carte vitale'),
('delay_invoice_days', '30', 'Nombre de jours avant facturation'),
('stock_warning_days', '14', 'Ancienneté déclenchant une alerte attente stock'),
('shop_name', 'Raja Audition', 'Nom de la boutique affiché dans Raja');
