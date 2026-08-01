-- =====================================================================
--  Migration — horaires et fermetures en base
--
--  À exécuter UNE FOIS sur une base déjà installée, pour ajouter les
--  trois tables qui rendent les disponibilités modifiables depuis
--  l'administration.
--
--  Inutile sur une base neuve : setup.php et schema.sql les créent déjà.
--
--  phpMyAdmin : onglet SQL, coller, Exécuter.
--  Railway    : préférez rouvrir setup.php, qui fait la même chose.
-- =====================================================================

-- Sous XAMPP, décommentez la ligne suivante si besoin :
-- USE salon;


-- --- Horaires hebdomadaires ---------------------------------------------
CREATE TABLE IF NOT EXISTS OpeningHours (
    DayOfWeek   TINYINT NOT NULL PRIMARY KEY,   -- 0 = dimanche ... 6 = samedi
    OpenMinute  SMALLINT NOT NULL DEFAULT 540,  -- minutes depuis minuit (540 = 09h00)
    CloseMinute SMALLINT NOT NULL DEFAULT 1140, -- 1140 = 19h00
    IsOpen      TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Fermetures exceptionnelles ------------------------------------------
CREATE TABLE IF NOT EXISTS Closures (
    Id        INT AUTO_INCREMENT PRIMARY KEY,
    StartDate DATE NOT NULL,
    EndDate   DATE NOT NULL,
    Reason    VARCHAR(160) NOT NULL DEFAULT '',
    INDEX idx_closure (StartDate, EndDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Réglages divers ------------------------------------------------------
CREATE TABLE IF NOT EXISTS Settings (
    SettingKey   VARCHAR(60) NOT NULL PRIMARY KEY,
    SettingValue VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =====================================================================
--  Valeurs de départ
--
--  INSERT IGNORE : si vous relancez ce script, vos réglages existants
--  ne seront pas écrasés.
-- =====================================================================

INSERT IGNORE INTO OpeningHours (DayOfWeek, OpenMinute, CloseMinute, IsOpen) VALUES
    (0,  540, 1140, 0),   -- dimanche  : fermé
    (1,  540, 1140, 0),   -- lundi     : fermé
    (2,  540, 1140, 1),   -- mardi     : 09h00 - 19h00
    (3,  540, 1140, 1),   -- mercredi
    (4,  540, 1140, 1),   -- jeudi
    (5,  540, 1140, 1),   -- vendredi
    (6,  540, 1020, 1);   -- samedi    : 09h00 - 17h00

INSERT IGNORE INTO Settings (SettingKey, SettingValue) VALUES
    ('BreakEnabled',  '1'),
    ('BreakStart',    '720'),   -- 12h00
    ('BreakEnd',      '780'),   -- 13h00
    ('SlotStep',      '15'),    -- granularité des créneaux, en minutes
    ('MinDelayHours', '2'),     -- délai minimum avant un rendez-vous
    ('DaysAhead',     '30');    -- horizon de réservation, en jours


-- =====================================================================
--  Vérification
-- =====================================================================

SELECT DayOfWeek, OpenMinute, CloseMinute, IsOpen FROM OpeningHours ORDER BY DayOfWeek;
SELECT SettingKey, SettingValue FROM Settings ORDER BY SettingKey;

-- Tout est ensuite modifiable depuis l'administration, menu
-- Disponibilités. Vous n'aurez plus à repasser par SQL.
