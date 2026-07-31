# À FAIRE EN PREMIER — installation en 8 étapes

Projet : site de réservation en ligne pour salon de beauté + interface
d'administration. Tout est dans ce dossier, rien d'autre à télécharger.

---

## 1. Placer le dossier

Déplacez ce dossier `salon` dans `C:\xampp\htdocs\`

Vous devez obtenir : `C:\xampp\htdocs\salon\`

## 2. Démarrer les serveurs

Ouvrez le **XAMPP Control Panel** et cliquez sur **Start** en face
d'**Apache**, puis en face de **MySQL**. Les deux lignes passent au vert.

Arrêtez Live Server dans VS Code, il ne sert plus à rien ici.

## 3. Créer la base de données

Ouvrez `http://localhost/phpmyadmin` → onglet **SQL**.

Ouvrez `schema.sql` dans un éditeur de texte, copiez tout, collez dans la zone
de texte, cliquez sur **Exécuter**.

À gauche doit apparaître une base `salon` contenant trois tables :
`users`, `prestations`, `reservations`.

## 4. Créer votre compte administrateur

Ouvrez `http://localhost/salon/admin/generate-hash.php`

Saisissez le mot de passe voulu, cliquez sur **Générer**. La page affiche une
requête `INSERT INTO users …`. Copiez-la, retournez dans phpMyAdmin (onglet
**SQL**), collez, **Exécuter**.

## 5. Supprimer le générateur

Supprimez le fichier `admin/generate-hash.php`.

Tant qu'il est en place, n'importe qui peut fabriquer un mot de passe.

## 6. Configurer votre salon

Ouvrez `includes/config.php` et renseignez :

- nom du salon, e-mail de notification, téléphone, adresse
- `HORAIRES` — jours d'ouverture et plages horaires
- `PAUSE_DEJEUNER` — ou `null` si vous n'en avez pas
- `DELAI_MIN_HEURES` — délai minimum avant un rendez-vous
- `FERMETURES` — congés et jours fériés

## 7. Adapter vos prestations

Connectez-vous sur `http://localhost/salon/admin/login.php`

Menu **Prestations** : neuf exemples sont pré-remplis. Modifiez intitulés,
durées et tarifs directement dans le tableau, puis **Enregistrer** ligne par
ligne. Ce que vous mettez ici s'affiche immédiatement côté client.

## 8. Tester le parcours complet

Ouvrez `http://localhost/salon/public/` dans un autre onglet.

Choisissez une prestation, un jour, un créneau, remplissez le formulaire,
confirmez. Retournez sur l'admin : la réservation apparaît dans le tableau de
bord, dans **Réservations** et dans le **Planning**.

---

## Les deux adresses à retenir

| | |
|---|---|
| Site client | `http://localhost/salon/public/` |
| Administration | `http://localhost/salon/admin/login.php` |

---

## Ce qui ne marchera pas en local (et c'est normal)

**Les e-mails ne partiront pas.** XAMPP n'a pas de serveur mail configuré, donc
`mail()` échoue silencieusement. La réservation s'enregistre bien et apparaît
dans l'admin — seul l'envoi ne se fait pas.

Pour y remédier, trois options détaillées dans `README.md` : MailHog pour
tester, un SMTP dans `php.ini`, ou PHPMailer pour la production.

---

## Contenu du dossier

```
salon/
├── A-FAIRE-EN-PREMIER.md    ← ce fichier
├── README.md                documentation complète
├── schema.sql               base de données à importer
├── api/database.php         connexion PDO
├── includes/                config, calcul des créneaux, e-mails
├── public/                  site visible par les clients
├── admin/                   interface du salon
└── _archives/               anciennes versions — supprimables
```
