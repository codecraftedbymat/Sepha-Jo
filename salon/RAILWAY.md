# Déployer sur Railway

Le projet fonctionne à l'identique en local (XAMPP) et en ligne (Railway) :
la connexion à la base et la configuration lisent les variables
d'environnement quand elles existent, et retombent sinon sur les valeurs
locales. Aucun fichier à modifier pour passer de l'un à l'autre.

---

## 1. Mettre le code sur GitHub

Railway déploie depuis un dépôt GitHub.

Dans VS Code, ouvrez le dossier `salon`, puis dans le terminal :

```bash
git init
git add .
git commit -m "Site de réservation salon"
```

Créez un dépôt vide sur github.com (bouton **New**), **sans** cocher « Add a
README ». GitHub vous affiche alors deux lignes à copier, du type :

```bash
git remote add origin https://github.com/VOTRE-COMPTE/salon.git
git branch -M main
git push -u origin main
```

## 2. Créer le projet Railway

Sur railway.com, connectez-vous avec GitHub, puis :

**New Project** → **Deploy from GitHub repo** → choisissez votre dépôt `salon`.

Railway repère le `Dockerfile` à la racine et construit l'image tout seul.
Le premier build prend deux à trois minutes.

## 3. Ajouter la base de données

Dans le même projet Railway : bouton **+ New** → **Database** → **Add MySQL**.

Railway crée le service et injecte automatiquement `MYSQLHOST`, `MYSQLPORT`,
`MYSQLUSER`, `MYSQLPASSWORD` et `MYSQLDATABASE` dans votre application.
C'est exactement ce que lit `api/database.php` — rien à configurer.

Si les variables n'apparaissent pas côté application, allez dans l'onglet
**Variables** du service web et ajoutez-les en référence :

```
MYSQLHOST     = ${{MySQL.MYSQLHOST}}
MYSQLPORT     = ${{MySQL.MYSQLPORT}}
MYSQLUSER     = ${{MySQL.MYSQLUSER}}
MYSQLPASSWORD = ${{MySQL.MYSQLPASSWORD}}
MYSQLDATABASE = ${{MySQL.MYSQLDATABASE}}
```

## 4. Créer les tables

Cliquez sur le service **MySQL** → onglet **Data** → **Query**.

Collez le contenu de `schema-railway.sql` (et non `schema.sql`, qui contient
un `CREATE DATABASE` inutile ici) et exécutez.

## 5. Ouvrir le site au public

Service web → **Settings** → **Networking** → **Generate Domain**.

Vous obtenez une adresse en `.up.railway.app`, en HTTPS.

## 6. Créer le compte administrateur

Ouvrez `https://VOTRE-DOMAINE/admin/generate-hash.php`, saisissez votre mot de
passe, copiez la requête `INSERT` affichée et exécutez-la dans **Data** →
**Query**.

Puis **supprimez le fichier** `public/admin/generate-hash.php` de votre dépôt
et refaites `git push` : Railway redéploie automatiquement.

## 7. Régler les variables du salon

Service web → onglet **Variables** → **New Variable**. Ajoutez ce dont vous
avez besoin :

| Variable | Exemple |
|---|---|
| `SALON_NOM` | Salon Éclat |
| `SALON_EMAIL` | contact@votre-salon.fr |
| `SALON_TEL` | 02 98 00 00 00 |
| `SALON_ADRESSE` | 12 rue des Lilas, 29200 Brest |
| `TZ` | Europe/Paris |

Les horaires d'ouverture, la pause déjeuner et les fermetures restent dans
`includes/config.php` : modifiez le fichier et poussez sur GitHub.

## 8. Faire partir les e-mails

C'est le point à ne pas négliger : **ni Railway ni XAMPP ne permettent
d'envoyer un e-mail sans SMTP externe**. Sans cette étape, les réservations
s'enregistrent et s'affichent dans l'admin, mais aucune confirmation ne part.

Créez un compte gratuit chez un expéditeur transactionnel — Brevo (ex
Sendinblue) offre 300 e-mails par jour, Mailgun et Resend ont aussi des
offres gratuites. Récupérez les identifiants SMTP et ajoutez dans
**Variables** :

| Variable | Exemple (Brevo) |
|---|---|
| `SMTP_HOST` | smtp-relay.brevo.com |
| `SMTP_PORT` | 587 |
| `SMTP_USER` | votre identifiant SMTP |
| `SMTP_PASS` | votre clé SMTP |
| `SMTP_FROM` | contact@votre-salon.fr |

PHPMailer est déjà installé par le `Dockerfile` : dès que `SMTP_HOST` est
renseigné, les e-mails partent avec le fichier `.ics` en pièce jointe.

---

## Vérifications après déploiement

1. `https://VOTRE-DOMAINE/` affiche la page de réservation avec vos prestations
2. `https://VOTRE-DOMAINE/admin/login.php` permet de se connecter
3. Une réservation test apparaît dans le tableau de bord
4. Le client reçoit sa confirmation par e-mail (si le SMTP est configuré)

## Différences avec la version locale

| | Local (XAMPP) | Railway |
|---|---|---|
| Racine web | `htdocs/salon/` | `public/` |
| Site client | `/salon/public/` | `/` |
| Admin | `/salon/admin/login.php` | `/admin/login.php` |
| Base | `salon` | `railway` |
| E-mails | à configurer | à configurer |
| HTTPS | non | oui, automatique |

## Si ça ne démarre pas

Ouvrez l'onglet **Deployments** → cliquez sur le déploiement → **View Logs**.
Les messages d'erreur PHP y apparaissent. Les causes les plus fréquentes sont
une variable MySQL absente et un `git push` oublié.

## Coût

Railway fonctionne à l'usage, avec un crédit d'essai mensuel. Un petit site
comme celui-ci consomme peu, mais la facturation évolue : vérifiez les tarifs
en cours sur railway.com/pricing avant de vous engager.
