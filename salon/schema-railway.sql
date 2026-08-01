-- =====================================================================
--  Tables — version Railway
--
--  Sur Railway, la base existe déjà (elle s'appelle « railway ») : ce
--  script ne crée donc PAS de base, seulement les tables.
--  À coller dans l'onglet « Data » → « Query » du service MySQL.
--
--  En local sous XAMPP, utilisez plutôt schema.sql.
-- =====================================================================

CREATE TABLE IF NOT EXISTS Users (
    Id         INT AUTO_INCREMENT PRIMARY KEY,
    Username   VARCHAR(50)  NOT NULL UNIQUE,
    Password   VARCHAR(255) NOT NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Services (
    Id    INT AUTO_INCREMENT PRIMARY KEY,
    Service   VARCHAR(120) NOT NULL,
    Delay INT NOT NULL,
    Prices  DECIMAL(6,2) DEFAULT NULL,
    Active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Reservations (
    Id            INT AUTO_INCREMENT PRIMARY KEY,
    ServiceId INT NOT NULL,
    ClientName    VARCHAR(120) NOT NULL,
    ClientEmail  VARCHAR(180) NOT NULL,
    ClientTel    VARCHAR(30)  NOT NULL,
    StartDate    DATETIME NOT NULL,
    EndDate      DATETIME NOT NULL,
    Status        ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    CreatedAt    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_resa_presta FOREIGN KEY (ServiceId) REFERENCES Services(id),
    INDEX idx_debut (StartDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
--  Catalogue Sepha-Jo by Lotte
--
--  Les TARIFS proviennent du site. Les DURÉES sont des estimations :
--  elles conditionnent l'espacement des créneaux, donc corrigez-les
--  depuis l'administration (menu Prestations) avant l'ouverture aux
--  clientes.
-- =====================================================================

INSERT INTO Services (Service, Delay, Prices) VALUES
    -- Gelaatsverzorgingen
    ('Deluxe gelaatsverzorging',                            75,  60.00),

    -- Dermaplanning
    ('Dermaplanning',                                       60,  70.00),
    ('Dermaplanning + Microneedling',                      120, 140.00),
    ('Dermaplanning + Kruidenpeeling',                     120, 140.00),

    -- Microneedling / Kruidenpeeling
    ('Microneedling',                                       75, 100.00),
    ('Kruidenpeeling',                                      75, 100.00),

    -- Pedicure (aan huis)
    ('Pedicure (aan huis)',                                 60,  35.00),
    ('Pedicure met gellak (aan huis)',                      75,  55.00),

    -- Lashlift
    ('Lashlift',                                            60,  45.00),
    ('Lashlift + tint',                                     75,  55.00),
    ('Verven wimpers',                                      15,  10.00),

    -- Brows
    ('Browlamination',                                      45,  45.00),
    ('Browlamination + tint',                               60,  55.00),
    ('Verven wenkbrauwen',                                  15,  10.00),

    -- Epilatie
    ('Epilatie wenkbrauwen',                                15,  10.00),
    ('Epilatie onderbenen',                                 30,  20.00),
    ('Epilatie boven- en onderbenen',                       45,  40.00),
    ('Epilatie kin',                                        15,  10.00),
    ('Epilatie bovenlip',                                   15,  10.00),
    ('Epilatie volledig gelaat',                            30,  30.00),
    ('Epilatie oksels',                                     20,  20.00),

    -- Relaxatie
    ('Sessie Oorkaarsen',                                   30,  10.00);

-- --- Compte administrateur ---------------------------------------------
-- Générez le hash sur https://VOTRE-DOMAINE/admin/generate-hash.php
-- puis exécutez ici la requête affichée, par exemple :
-- INSERT INTO users (username, password) VALUES ('lotte', '$2y$10$...');
