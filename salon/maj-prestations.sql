-- =====================================================================
--  Sepha-Jo by Lotte — mise a jour du catalogue
--
--  A executer dans phpMyAdmin : onglet SQL, coller, Executer.
--  phpMyAdmin accepte plusieurs instructions a la suite.
--
--  Choisissez UNE des deux methodes et supprimez l'autre.
-- =====================================================================

USE salon;
-- Si votre base porte un autre nom, corrigez la ligne ci-dessus.
-- Sur Railway, supprimez-la : la base est deja selectionnee.


-- =====================================================================
--  METHODE 1 — REMISE A ZERO   (base de test uniquement)
--
--  /!\  DESTRUCTIF : efface toutes les reservations existantes.
--       Les reservations pointent vers les prestations : il faut les
--       retirer d'abord, sinon la cle etrangere bloque la suppression.
-- =====================================================================

DELETE FROM Reservations;
DELETE FROM Services;

ALTER TABLE Reservations AUTO_INCREMENT = 1;
ALTER TABLE Services     AUTO_INCREMENT = 1;

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


-- =====================================================================
--  METHODE 2 — SANS PERTE   (si de vrais rendez-vous existent)
--
--  Masque l'ancien catalogue au lieu de le supprimer : les reservations
--  passees gardent leur intitule. Decommentez ce bloc et supprimez la
--  methode 1 ci-dessus.
-- =====================================================================

-- UPDATE Services SET Active = 0;
--
-- INSERT INTO Services (Service, Delay, Prices, Active) VALUES
--     ('Deluxe gelaatsverzorging',        75,  60.00, 1),
--     ('Dermaplanning',                   60,  70.00, 1),
--     ('Dermaplanning + Microneedling',  120, 140.00, 1),
--     ('Dermaplanning + Kruidenpeeling', 120, 140.00, 1),
--     ('Microneedling',                   75, 100.00, 1),
--     ('Kruidenpeeling',                  75, 100.00, 1),
--     ('Pedicure (aan huis)',             60,  35.00, 1),
--     ('Pedicure met gellak (aan huis)',  75,  55.00, 1),
--     ('Lashlift',                        60,  45.00, 1),
--     ('Lashlift + tint',                 75,  55.00, 1),
--     ('Verven wimpers',                  15,  10.00, 1),
--     ('Browlamination',                  45,  45.00, 1),
--     ('Browlamination + tint',           60,  55.00, 1),
--     ('Verven wenkbrauwen',              15,  10.00, 1),
--     ('Epilatie wenkbrauwen',            15,  10.00, 1),
--     ('Epilatie bovenlip',               15,  10.00, 1),
--     ('Epilatie kin',                    15,  10.00, 1),
--     ('Epilatie volledig gelaat',        30,  30.00, 1),
--     ('Epilatie oksels',                 20,  20.00, 1),
--     ('Epilatie onderbenen',             30,  20.00, 1),
--     ('Epilatie boven- en onderbenen',   45,  40.00, 1),
--     ('Sessie Oorkaarsen',               30,  10.00, 1);


-- =====================================================================
--  VERIFICATION — 22 lignes attendues
-- =====================================================================

SELECT Id, Service, Delay, Prices, Active
FROM Services
ORDER BY Active DESC, Service ASC;
