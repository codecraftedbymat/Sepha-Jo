# Archives — versions antérieures

Ce dossier ne sert **à rien pour faire fonctionner le site**. Vous pouvez le
supprimer sans conséquence. Il conserve simplement les fichiers produits aux
étapes précédentes du projet, au cas où vous voudriez y revenir.

**Ne copiez pas ces fichiers dans le projet actif** : ils sont incompatibles
avec la version finale (nom de base de données différent, dépendances à un
template absent, stockage qui ne fonctionne pas sur un serveur).

| Fichier | Ce que c'était | Remplacé par |
|---|---|---|
| `reservation-salon.html` | Première maquette de réservation, page unique autonome. Stockait les réservations via une API disponible uniquement dans l'aperçu de Claude — **ne fonctionne pas sur un serveur web**. | `public/index.php` |
| `login-iseinimmo.php` | Page de connexion reprenant le template ISEN-Immo (classes `login100-*`). | `admin/login.php` |
| `main-login-iseinimmo.css` | Feuille de style écrite pour ces classes `login100-*`. | `admin/assets/admin.css` |
| `database-ancien.php` | Connexion PDO pointant sur une base nommée `sepha_jo`. | `api/database.php` (base `salon`) |
| `schema-ancien.sql` | Premier schéma SQL, base `sepha_jo`. | `schema.sql` (base `salon`) |
| `generate-hash.php` | Générateur de hash — identique à celui du dossier `admin/`. | `admin/generate-hash.php` |

## Différence principale à retenir

La maquette HTML du début et le site final font la même chose à l'écran, mais
pas du tout de la même façon :

- **`reservation-salon.html`** gardait les réservations dans le navigateur.
  Rien n'arrivait jamais côté salon.
- **`public/index.php`** enregistre en base MySQL, verrouille le créneau pour
  qu'aucun autre client ne le prenne, envoie les e-mails et fait apparaître le
  rendez-vous dans l'administration.
