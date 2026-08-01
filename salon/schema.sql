-- =====================================================================
--  Sepha-Jo by Lotte — base de donnees complete (version locale XAMPP)
--  A importer dans phpMyAdmin : onglet SQL, coller, Executer.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS salon
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE salon;

CREATE TABLE IF NOT EXISTS Users (
    Id        INT AUTO_INCREMENT PRIMARY KEY,
    Username  VARCHAR(50)  NOT NULL UNIQUE,
    Password  VARCHAR(255) NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Services (
    Id      INT AUTO_INCREMENT PRIMARY KEY,
    Service VARCHAR(120) NOT NULL,
    Delay   INT NOT NULL,               -- duree en minutes
    Prices  DECIMAL(6,2) DEFAULT NULL,
    Active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Reservations (
    Id          INT AUTO_INCREMENT PRIMARY KEY,
    ServiceId   INT NOT NULL,
    ClientName  VARCHAR(120) NOT NULL,
    ClientEmail VARCHAR(180) NOT NULL,
    ClientTel   VARCHAR(30)  NOT NULL,
    StartDate   DATETIME NOT NULL,
    EndDate     DATETIME NOT NULL,
    Status      ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    CreatedAt   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservation_service FOREIGN KEY (ServiceId) REFERENCES Services(Id),
    INDEX idx_startdate (StartDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS OpeningHours (
    DayOfWeek   TINYINT NOT NULL PRIMARY KEY,   -- 0 = dimanche ... 6 = samedi
    OpenMinute  SMALLINT NOT NULL DEFAULT 540,  -- minutes depuis minuit (540 = 09h00)
    CloseMinute SMALLINT NOT NULL DEFAULT 1140, -- 1140 = 19h00
    IsOpen      TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Closures (
    Id        INT AUTO_INCREMENT PRIMARY KEY,
    StartDate DATE NOT NULL,
    EndDate   DATE NOT NULL,
    Reason    VARCHAR(160) NOT NULL DEFAULT '',
    INDEX idx_closure (StartDate, EndDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Settings (
    SettingKey   VARCHAR(60) NOT NULL PRIMARY KEY,
    SettingValue VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Catalogue Sepha-Jo by Lotte ---------------------------------------
--  Tarifs repris du site. Les DUREES (Delay) sont des estimations :
--  corrigez-les depuis l'administration, menu Prestations.


-- --- Horaires par defaut : ferme dimanche et lundi ----------------------
INSERT IGNORE INTO OpeningHours (DayOfWeek, OpenMinute, CloseMinute, IsOpen) VALUES
    (0,  540, 1140, 0),   -- dimanche  : ferme
    (1,  540, 1140, 0),   -- lundi     : ferme
    (2,  540, 1140, 1),   -- mardi     : 09h00 - 19h00
    (3,  540, 1140, 1),   -- mercredi
    (4,  540, 1140, 1),   -- jeudi
    (5,  540, 1140, 1),   -- vendredi
    (6,  540, 1020, 1);   -- samedi    : 09h00 - 17h00

-- --- Reglages de reservation -------------------------------------------
INSERT IGNORE INTO Settings (SettingKey, SettingValue) VALUES
    ('BreakEnabled',  '1'),
    ('BreakStart',    '720'),   -- 12h00
    ('BreakEnd',      '780'),   -- 13h00
    ('SlotStep',      '15'),    -- granularite des creneaux, en minutes
    ('MinDelayHours', '2'),     -- delai minimum avant un rendez-vous
    ('DaysAhead',     '30');    -- horizon de reservation, en jours

-- Tout ceci est ensuite modifiable depuis l'administration,
-- menu Disponibilites : plus besoin de repasser par SQL.

INSERT INTO Services (Service, Delay, Prices) VALUES
    ('Deluxe gelaatsverzorging',        75,  60.00),
    ('Dermaplanning',                   60,  70.00),
    ('Dermaplanning + Microneedling',  120, 140.00),
    ('Dermaplanning + Kruidenpeeling', 120, 140.00),
    ('Microneedling',                   75, 100.00),
    ('Kruidenpeeling',                  75, 100.00),
    ('Pedicure (aan huis)',             60,  35.00),
    ('Pedicure met gellak (aan huis)',  75,  55.00),
    ('Lashlift',                        60,  45.00),
    ('Lashlift + tint',                 75,  55.00),
    ('Verven wimpers',                  15,  10.00),
    ('Browlamination',                  45,  45.00),
    ('Browlamination + tint',           60,  55.00),
    ('Verven wenkbrauwen',              15,  10.00),
    ('Epilatie wenkbrauwen',            15,  10.00),
    ('Epilatie bovenlip',               15,  10.00),
    ('Epilatie kin',                    15,  10.00),
    ('Epilatie volledig gelaat',        30,  30.00),
    ('Epilatie oksels',                 20,  20.00),
    ('Epilatie onderbenen',             30,  20.00),
    ('Epilatie boven- en onderbenen',   45,  40.00),
    ('Sessie Oorkaarsen',               30,  10.00);

-- --- Compte administrateur ---------------------------------------------
-- Generez le hash sur /admin/generate-hash.php, puis :
-- INSERT INTO Users (Username, Password) VALUES ('lotte', '$2y$10$...');
