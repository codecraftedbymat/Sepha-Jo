# Faire partir les e-mails

Par défaut, aucun e-mail n'est envoyé : ni XAMPP ni Railway ne disposent d'un
serveur mail. Les réservations s'enregistrent correctement et apparaissent
dans l'administration — seul l'envoi ne se fait pas.

Il faut passer par un service SMTP externe. Comptez dix minutes.

---

## 1. Créer un compte chez un expéditeur

Trois services avec une offre gratuite suffisante :

| Service | Gratuit | Remarque |
|---|---|---|
| Brevo | 300 e-mails / jour | le plus simple pour démarrer |
| Mailgun | selon l'offre en cours | orienté développeurs |
| Resend | selon l'offre en cours | interface moderne |

Les conditions de ces offres évoluent : vérifiez sur leur site avant de
choisir.

Après inscription, cherchez la section **SMTP** de votre compte. Vous y
trouverez quatre informations : un serveur (`smtp-relay.brevo.com` chez
Brevo), un port (`587`), un identifiant et une clé.

Attention : la clé SMTP n'est pas votre mot de passe de connexion au site du
service, c'est une clé distincte à générer.

---

## 2A. En local — sans installer Composer

C'est la voie la plus courte si vous voulez seulement tester.

XAMPP embarque un utilitaire d'envoi qu'il suffit de configurer.

**Ouvrez `C:\xampp\php\php.ini`** dans le Bloc-notes. Cherchez la section
`[mail function]` et remplacez son contenu par :

```ini
[mail function]
SMTP=smtp-relay.brevo.com
smtp_port=587
sendmail_from=contact@votre-salon.fr
sendmail_path="\"C:\xampp\sendmail\sendmail.exe\" -t"
```

**Ouvrez ensuite `C:\xampp\sendmail\sendmail.ini`** et renseignez :

```ini
smtp_server=smtp-relay.brevo.com
smtp_port=587
auth_username=votre-identifiant-smtp
auth_password=votre-cle-smtp
force_sender=contact@votre-salon.fr
```

**Redémarrez Apache** depuis le XAMPP Control Panel (Stop puis Start).

Refaites une réservation test : l'e-mail doit arriver.

---

## 2B. En local — avec Composer (identique à la production)

Cette voie utilise PHPMailer, exactement comme sur Railway. Un peu plus long
à mettre en place, mais votre environnement local se comporte alors comme le
serveur en ligne.

**Installez Composer** depuis getcomposer.org (installeur Windows).

**Dans le dossier `salon`**, exécutez :

```bash
composer install
```

Un dossier `vendor/` apparaît. Il est exclu de Git, c'est normal.

**Copiez `includes/config.local.example.php`** sous le nom
`includes/config.local.php`, et renseignez vos identifiants SMTP dedans.

Ce fichier est exclu de Git : vos identifiants ne partiront jamais sur
GitHub. C'est la raison d'être de ce mécanisme.

Refaites une réservation test.

---

## 3. En ligne, sur Railway

Aucun fichier à créer : tout passe par les variables d'environnement.

Service web → onglet **Variables** → **New Variable** :

| Variable | Valeur |
|---|---|
| `SMTP_HOST` | smtp-relay.brevo.com |
| `SMTP_PORT` | 587 |
| `SMTP_USER` | votre identifiant SMTP |
| `SMTP_PASS` | votre clé SMTP |
| `SMTP_FROM` | contact@votre-salon.fr |

PHPMailer est déjà installé par le `Dockerfile`. Dès que `SMTP_HOST` est
renseigné, les envois se font en SMTP authentifié.

---

## Si les e-mails partent mais arrivent en spam

C'est fréquent au début, et ça se règle du côté du service d'envoi, pas du
code.

Dans votre compte Brevo (ou équivalent), cherchez **Domaines** et suivez la
procédure d'authentification : elle vous demande d'ajouter des
enregistrements SPF et DKIM chez votre fournisseur de nom de domaine. Une
fois validés, vos messages sont reconnus comme légitimes.

Tant que ce n'est pas fait, utilisez une adresse d'expéditeur du domaine que
vous contrôlez, et évitez les adresses en `@gmail.com` comme expéditeur : les
serveurs les rejettent ou les classent en indésirable.

---

## Vérifier ce qui se passe

Le code n'interrompt jamais une réservation à cause d'un e-mail : si l'envoi
échoue, la réservation reste enregistrée et le client voit sa confirmation à
l'écran.

Les échecs sont écrits dans le journal d'erreurs PHP :

- en local : `C:\xampp\php\logs\php_error_log` ou `C:\xampp\apache\logs\error.log`
- sur Railway : onglet **Deployments** → **View Logs**

Cherchez les lignes commençant par « Envoi SMTP echoue » ou « E-mail non
envoye ».

---

## Une alternative pour tester sans rien envoyer

MailHog et Mailpit sont de petits programmes qui interceptent les e-mails et
les affichent dans une page web locale. Rien ne part réellement, aucune clé
n'est nécessaire, et vous voyez le rendu exact de vos messages avec la pièce
jointe `.ics`.

Pratique pendant le développement, inutile en production.
