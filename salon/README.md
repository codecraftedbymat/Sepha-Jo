# Salon — site de réservation + interface d'administration

## Arborescence

```
salon/
├── schema.sql                  base de données à importer
├── api/
│   └── database.php            connexion PDO (nom de la base ici)
├── includes/
│   ├── config.php              ← LE fichier à personnaliser
│   ├── creneaux.php            calcul des disponibilités
│   └── notifications.php       e-mails + fichier .ics
└── public/                     racine web
    ├── index.php               site client
    ├── assets/booking.css · booking.js
    ├── api/creneaux.php · reserver.php · agenda.php
    └── admin/                  partie réservée au salon
        ├── login.php · logout.php · generate-hash.php
        ├── index.php           tableau de bord
        ├── reservations.php · prestations.php · planning.php
        ├── includes/auth.php · layout.php
        └── assets/admin.css
```

## Installation

1. Copier le dossier dans `C:\xampp\htdocs\salon`
2. Démarrer **Apache** et **MySQL** depuis le XAMPP Control Panel
3. Ouvrir `http://localhost/phpmyadmin`, onglet **SQL**, coller le contenu de
   `schema.sql` et exécuter
4. Ouvrir `http://localhost/salon/public/admin/generate-hash.php`, saisir le mot de
   passe voulu, exécuter la requête `INSERT` affichée dans phpMyAdmin
5. **Supprimer** `public/admin/generate-hash.php`
6. Ouvrir `includes/config.php` et renseigner : nom du salon, e-mail, téléphone,
   adresse, horaires d'ouverture, pause déjeuner, fermetures exceptionnelles

Le site client est alors sur `http://localhost/salon/public/`
et l'administration sur `http://localhost/salon/public/admin/login.php`.

## Envoi des e-mails en local

La fonction `mail()` de PHP ne fonctionne pas sous XAMPP sans configuration :
les réservations s'enregistrent bien, mais aucun e-mail ne part.

Trois solutions, de la plus simple à la plus robuste :

- **Tester sans envoyer** : installer MailHog ou Mailpit, qui capture les
  e-mails et les affiche dans une interface web locale.
- **Passer par un vrai SMTP** : renseigner `[mail function]` dans
  `C:\xampp\php\php.ini` (`SMTP`, `smtp_port`, `sendmail_from`).
- **Utiliser PHPMailer** (recommandé en production) : `composer require
  phpmailer/phpmailer`, puis remplacer le corps de la fonction
  `envoyer_mail()` dans `includes/notifications.php` par un envoi SMTP
  authentifié. C'est la seule méthode fiable pour que les e-mails ne
  finissent pas en spam.

En hébergement mutualisé classique, `mail()` fonctionne souvent directement.

## Agenda de l'entreprise

Chaque e-mail (client et salon) contient un fichier `.ics` en pièce jointe :
l'ouvrir crée le rendez-vous dans Google Agenda, Outlook ou Apple Calendar,
avec un rappel une heure avant.

Pour une synchronisation totalement automatique, renseigner `WEBHOOK_AGENDA`
dans `includes/config.php` avec l'URL d'un scénario Zapier, Make ou Google
Apps Script : la réservation lui est envoyée en JSON à chaque validation.

## Points d'attention

- Deux clients ne peuvent pas obtenir le même créneau : la vérification finale
  se fait en base, dans une transaction verrouillée (`SELECT … FOR UPDATE`).
- Les créneaux tiennent compte de la durée de la prestation, des horaires du
  jour, de la pause déjeuner, des fermetures et du délai minimum de réservation.
- Une prestation masquée depuis l'admin disparaît immédiatement du site client.
- Avant mise en ligne : supprimer `generate-hash.php`, changer le mot de passe
  MySQL `root`, et servir le site en HTTPS.


## Mise en ligne

Pour héberger le site sur Railway plutôt qu'en local, voir `RAILWAY.md`.
Le code est identique : la connexion à la base et la configuration lisent
les variables d'environnement quand elles existent.
