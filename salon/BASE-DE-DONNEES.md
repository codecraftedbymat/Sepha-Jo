# Nomenclature de la base de données

Le schéma utilise des noms anglais en PascalCase. Tout le code applicatif
suit cette convention.

## Tables et colonnes

### Users

| Colonne | Type | Rôle |
|---|---|---|
| `Id` | INT | clé primaire |
| `Username` | VARCHAR(50) | identifiant de connexion, unique |
| `Password` | VARCHAR(255) | empreinte `password_hash()`, jamais le mot de passe |
| `CreatedAt` | TIMESTAMP | date de création |

### Services

| Colonne | Type | Rôle |
|---|---|---|
| `Id` | INT | clé primaire |
| `Service` | VARCHAR(120) | intitulé de la prestation |
| `Delay` | INT | **durée en minutes** — détermine l'espacement des créneaux |
| `Prices` | DECIMAL(6,2) | tarif, peut être NULL |
| `Active` | TINYINT(1) | 1 = visible par les clientes, 0 = masquée |

### Reservations

| Colonne | Type | Rôle |
|---|---|---|
| `Id` | INT | clé primaire |
| `ServiceId` | INT | clé étrangère vers `Services(Id)` |
| `ClientName` | VARCHAR(120) | nom de la cliente |
| `ClientEmail` | VARCHAR(180) | adresse e-mail |
| `ClientTel` | VARCHAR(30) | téléphone |
| `StartDate` | DATETIME | début du rendez-vous |
| `EndDate` | DATETIME | fin, calculée à partir de `Delay` |
| `Status` | ENUM | `confirmed` ou `cancelled` |
| `CreatedAt` | TIMESTAMP | date d'enregistrement |

## Trois corrections apportées au script fourni

**`USE` sans nom de base.** La ligne `USE` seule provoque une erreur de
syntaxe. Elle a été retirée de `schema-railway.sql` : sur Railway la base est
déjà sélectionnée. Dans `schema.sql`, destiné à XAMPP, elle vaut `USE salon;`.

**`NVARCHAR` n'existe pas en MySQL.** C'est un type SQL Server. `ClientEmail`
et `ClientTel` utilisent `VARCHAR`, qui stocke déjà l'UTF-8 grâce au jeu de
caractères `utf8mb4` de la table.

**`REFERENCES Services(id)` en minuscule.** La colonne s'appelle `Id`. MySQL
tolère la casse sur les colonnes, mais autant rester cohérent.

## Un point de vigilance : la casse des noms de tables

Sous Windows, MySQL ne distingue pas `services` de `Services`. Sous Linux —
donc sur Railway, dans le conteneur Docker — **il les distingue**.

Conséquence : une requête écrite `FROM services` fonctionnera en local et
échouera en ligne avec « Table doesn't exist ». Tout le code respecte
scrupuleusement la casse `Users`, `Services`, `Reservations` ; conservez-la si
vous ajoutez des requêtes.

## Ce qui n'a pas changé

Les clés internes qui ne correspondent à aucune colonne gardent leur nom
français, car elles sont calculées et non lues en base :

- `prestation` — alias de `p.Service` dans les jointures
- `date_longue`, `heure_debut`, `heure_fin` — mises en forme pour l'affichage
- `libre`, `debut`, `label` — structure des créneaux calculés

Les noms de fichiers PHP (`prestations.php`, `reservations.php`) et les champs
de formulaire HTML restent également inchangés.

## Migration d'une base existante

Si vous avez déjà des tables avec l'ancienne nomenclature, exécutez ceci dans
phpMyAdmin avant de déployer le nouveau code :

```sql
RENAME TABLE users TO Users, prestations TO Services, reservations TO Reservations;

ALTER TABLE Users
    CHANGE id         Id        INT AUTO_INCREMENT,
    CHANGE username   Username  VARCHAR(50)  NOT NULL,
    CHANGE password   Password  VARCHAR(255) NOT NULL,
    CHANGE created_at CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE Services
    CHANGE id    Id      INT AUTO_INCREMENT,
    CHANGE nom   Service VARCHAR(120) NOT NULL,
    CHANGE duree Delay   INT NOT NULL,
    CHANGE prix  Prices  DECIMAL(6,2) DEFAULT NULL,
    CHANGE actif Active  TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE Reservations
    CHANGE id            Id          INT AUTO_INCREMENT,
    CHANGE prestation_id ServiceId   INT NOT NULL,
    CHANGE client_nom    ClientName  VARCHAR(120) NOT NULL,
    CHANGE client_email  ClientEmail VARCHAR(180) NOT NULL,
    CHANGE client_tel    ClientTel   VARCHAR(30)  NOT NULL,
    CHANGE date_debut    StartDate   DATETIME NOT NULL,
    CHANGE date_fin      EndDate     DATETIME NOT NULL,
    CHANGE statut        Status      ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    CHANGE created_at    CreatedAt   TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

UPDATE Reservations SET Status = 'confirmed' WHERE Status = 'confirmee';
UPDATE Reservations SET Status = 'cancelled' WHERE Status = 'annulee';
```

Plus simple si la base ne contient que des données de test : supprimez-la et
relancez `setup.php`.
