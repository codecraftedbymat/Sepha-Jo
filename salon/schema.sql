-- =====================================================================
--  Base de données de l'interface admin — Salon
--  À importer dans phpMyAdmin (onglet SQL).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS salon
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE salon;

CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prestations (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    nom   VARCHAR(120) NOT NULL,
    duree INT NOT NULL,
    prix  DECIMAL(6,2) DEFAULT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    prestation_id INT NOT NULL,
    client_nom    VARCHAR(120) NOT NULL,
    client_email  VARCHAR(180) NOT NULL,
    client_tel    VARCHAR(30)  NOT NULL,
    date_debut    DATETIME NOT NULL,
    date_fin      DATETIME NOT NULL,
    statut        ENUM('confirmee','annulee') NOT NULL DEFAULT 'confirmee',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_resa_presta FOREIGN KEY (prestation_id) REFERENCES prestations(id),
    INDEX idx_debut (date_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Catalogue de départ : 9 prestations (durées et tarifs à adapter) ---
INSERT INTO prestations (nom, duree, prix) VALUES
    ('Manucure express',           30, 25.00),
    ('Manucure semi-permanent',    60, 40.00),
    ('Pédicure spa',               60, 45.00),
    ('Épilation sourcils',         15, 12.00),
    ('Extension de cils',          90, 65.00),
    ('Soin du visage signature',   60, 55.00),
    ('Massage relaxant du dos',    45, 40.00),
    ('Maquillage jour',            45, 35.00),
    ('Épilation jambes complètes', 45, 30.00);

-- --- Compte administrateur ----------------------------------------------
-- Générez d'abord le hash avec admin/generate-hash.php, puis décommentez :
-- INSERT INTO users (username, password) VALUES ('admin', 'COLLEZ_LE_HASH_ICI');
