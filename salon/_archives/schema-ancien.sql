-- =====================================================================
--  Base de données — Salon (projet Sepha-Jo)
--  À importer dans phpMyAdmin (onglet "Importer") ou à coller dans SQL.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS sepha_jo
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sepha_jo;

-- ---------------------------------------------------------------------
--  Comptes administrateurs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,          -- hash password_hash(), jamais en clair
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
--  Prestations proposées
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prestations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(120) NOT NULL,
    duree       INT NOT NULL,                   -- en minutes
    prix        DECIMAL(6,2) DEFAULT NULL,
    actif       TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
--  Réservations
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reservations (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    prestation_id  INT NOT NULL,
    client_nom     VARCHAR(120) NOT NULL,
    client_email   VARCHAR(180) NOT NULL,
    client_tel     VARCHAR(30)  NOT NULL,
    date_debut     DATETIME NOT NULL,
    date_fin       DATETIME NOT NULL,
    statut         ENUM('confirmee','annulee') NOT NULL DEFAULT 'confirmee',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservation_prestation
        FOREIGN KEY (prestation_id) REFERENCES prestations(id),
    -- Empêche deux réservations exactement au même horaire de départ
    UNIQUE KEY uq_creneau (date_debut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
--  Jeu de données de départ : les 9 prestations (à adapter)
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
--  Compte admin
--  N'insérez PAS le mot de passe en clair : générez d'abord le hash avec
--  admin/generate-hash.php, puis collez-le à la place de la valeur ci-dessous.
-- ---------------------------------------------------------------------
-- INSERT INTO users (username, password) VALUES
--     ('admin', 'COLLEZ_ICI_LE_HASH_GENERE');
