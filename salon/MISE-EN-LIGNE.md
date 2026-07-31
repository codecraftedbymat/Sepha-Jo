# Mise en ligne — GitHub puis Railway

Procédure complète, en partant du principe que le développement local est
laissé de côté. Comptez 45 minutes la première fois.

---

# PHASE A — Préparer le dossier

## A1. Repartir d'une base propre

Extrayez `salon.zip`. Dans `C:\Users\bilob\OneDrive\Documents\GitHub\Sepha-Jo\`,
supprimez l'ancien dossier `salon` et remplacez-le par le nouveau.

Cette étape évite de déployer un mélange de versions accumulées au fil des
corrections.

## A2. Renseigner vos horaires

Ouvrez `salon\includes\config.php` et ajustez :

- `HORAIRES` — les jours et plages d'ouverture réels du salon
- `PAUSE_DEJEUNER` — ou `null` si le salon n'en a pas
- `DELAI_MIN_HEURES` — délai minimum avant un rendez-vous
- `FERMETURES` — congés connus

Ne touchez pas aux lignes `define(...)` du haut : nom, e-mail, téléphone et
adresse seront fournis par les variables Railway.

## A3. Vérifier ce qui ne doit pas partir

Le fichier `.gitignore` exclut déjà `vendor/`, `composer.lock` et
`includes/config.local.php`. Vérifiez simplement qu'il n'y a **pas** de
`composer.phar` ni de `config.local.php` dans le dossier — s'ils y sont,
supprimez le premier, le second est ignoré automatiquement.

---

# PHASE B — GitHub

## B1. Committer

Ouvrez le dossier `Sepha-Jo` dans VS Code. Terminal (Command Prompt ou
PowerShell, pas Ubuntu) :

```bash
git add .
git commit -m "Site de reservation salon"
git push
```

Si Git réclame une branche : `git push -u origin main`.

## B2. Vérifier

Ouvrez votre dépôt sur github.com. Vous devez voir le dossier `salon`
contenant `Dockerfile`, `public`, `includes`, `api`.

Vérifiez qu'il n'y a **pas** de fichier `config.local.php` en ligne. S'il
apparaît, c'est que le `.gitignore` n'a pas été pris en compte : supprimez-le
du dépôt avant d'aller plus loin.

---

# PHASE C — Railway

## C1. Créer le projet

Sur railway.com, connectez-vous avec GitHub.

**New Project** → **Deploy from GitHub repo** → sélectionnez `Sepha-Jo`.

Le premier build échoue : c'est normal, Railway cherche le `Dockerfile` à la
racine du dépôt alors qu'il est dans `salon/`.

## C2. Indiquer le sous-dossier

Cliquez sur le service → **Settings** → section **Source** → **Root
Directory** → saisissez `/salon` → validez.

Railway relance le build. Deux à trois minutes.

Suivez la progression dans **Deployments**. Le déploiement doit finir en
vert.

## C3. Ajouter la base de données

Bouton **+ New** (ou **Create**) → **Database** → **Add MySQL**.

Une carte MySQL apparaît. Railway injecte automatiquement les identifiants
dans votre application : rien à configurer.

## C4. Vérifier les variables de connexion

Service web → onglet **Variables**. Vous devez voir :

```
MYSQLHOST  MYSQLPORT  MYSQLUSER  MYSQLPASSWORD  MYSQLDATABASE
```

Si elles manquent, ajoutez-les une par une avec ces valeurs exactes :

| Variable | Valeur |
|---|---|
| `MYSQLHOST` | `${{MySQL.MYSQLHOST}}` |
| `MYSQLPORT` | `${{MySQL.MYSQLPORT}}` |
| `MYSQLUSER` | `${{MySQL.MYSQLUSER}}` |
| `MYSQLPASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
| `MYSQLDATABASE` | `${{MySQL.MYSQLDATABASE}}` |

## C5. Créer les tables

Cliquez sur la carte **MySQL** → onglet **Data** → bouton **Query**.

Collez le contenu de `schema-railway.sql` — **ce fichier-là**, pas
`schema.sql` qui contient un `CREATE DATABASE` inutile ici — puis exécutez.

Vérifiez dans **Data** que `users`, `prestations` et `reservations`
apparaissent.

## C6. Ouvrir le site au public

Service web → **Settings** → **Networking** → **Generate Domain**.

Notez l'adresse obtenue, en `.up.railway.app`. Elle est en HTTPS.

## C7. Renseigner les informations du salon

Service web → **Variables** → **New Variable**, une par une :

| Variable | Exemple |
|---|---|
| `SALON_NOM` | Salon Éclat |
| `SALON_EMAIL` | bilobaalain@gmail.com |
| `SALON_TEL` | 02 98 00 00 00 |
| `SALON_ADRESSE` | 12 rue des Lilas, 29200 Brest |
| `TZ` | Europe/Paris |

`SALON_EMAIL` est l'adresse qui recevra les notifications de nouvelle
réservation : mettez la vôtre pour les tests.

## C8. Configurer l'envoi des e-mails

Toujours dans **Variables** :

| Variable | Valeur |
|---|---|
| `SMTP_HOST` | smtp-relay.brevo.com |
| `SMTP_PORT` | 587 |
| `SMTP_USER` | b3fb5c001@smtp-brevo.com |
| `SMTP_PASS` | votre clé SMTP Brevo |
| `SMTP_FROM` | bilobaalain@gmail.com |

Deux conditions côté Brevo, sans lesquelles rien ne partira :

1. La clé SMTP doit être **active** (si vous avez supprimé l'ancienne après
   l'avoir partagée, utilisez la nouvelle)
2. L'adresse `SMTP_FROM` doit être **validée** : Brevo → **Expéditeurs,
   domaine, IP** → ajoutez l'adresse et cliquez le lien de confirmation reçu
   par e-mail

Railway redéploie automatiquement à chaque ajout de variable.

## C9. Créer le compte administrateur

Ouvrez `https://VOTRE-DOMAINE/admin/generate-hash.php`.

Saisissez l'identifiant et le mot de passe voulus, cliquez **Générer**,
copiez la requête **A** (`INSERT`).

Retournez dans MySQL → **Data** → **Query**, collez, exécutez.

## C10. Supprimer les fichiers sensibles

Sur votre machine, supprimez ces deux fichiers du dossier `salon` :

- `public/admin/generate-hash.php`
- `public/test-mail.php` (gardez-le si l'étape D3 échoue, supprimez-le
  ensuite)

Puis :

```bash
git add .
git commit -m "Suppression des outils de configuration"
git push
```

Railway redéploie tout seul.

---

# PHASE D — Vérifier

## D1. Le site client

Ouvrez `https://VOTRE-DOMAINE/`

La page de réservation s'affiche avec les 9 prestations.

## D2. L'administration

`https://VOTRE-DOMAINE/admin/login.php`

Connectez-vous. Allez dans **Prestations** et ajustez intitulés, durées et
tarifs à ceux du salon.

## D3. Le parcours complet

Dans une fenêtre de navigation privée, ouvrez le site client et faites une
réservation de bout en bout avec votre vraie adresse e-mail.

Trois choses doivent se produire :

1. L'écran de confirmation s'affiche
2. La réservation apparaît dans l'admin (tableau de bord, réservations,
   planning)
3. Vous recevez deux e-mails : la confirmation client et la notification
   salon

Si les points 1 et 2 fonctionnent mais pas le 3, ouvrez
`https://VOTRE-DOMAINE/test-mail.php` : la page indique la voie d'envoi
utilisée et les valeurs de configuration. Pensez à la supprimer ensuite.

---

# Au quotidien

Vous modifiez le code sur votre machine, puis :

```bash
git add .
git commit -m "votre message"
git push
```

Railway redéploie automatiquement. Vous ne retournerez dans son interface que
pour changer une variable.

Pour consulter les erreurs : service web → **Deployments** → cliquez le
déploiement → **View Logs**.

---

# En cas de blocage

| Symptôme | Cause la plus fréquente |
|---|---|
| Build en échec | Root Directory oublié (étape C2) |
| Page blanche ou erreur 500 | Variables MySQL absentes (C4) ou tables non créées (C5) |
| « Service temporairement indisponible » | La base n'est pas jointe : vérifiez C3 et C4 |
| Site accessible mais aucune prestation | `schema-railway.sql` non exécuté (C5) |
| Connexion admin refusée | Compte non créé (C9) |
| Aucun e-mail | Variables SMTP (C8), clé inactive, ou expéditeur non validé chez Brevo |
