<?php
/**
 * MODÈLE — à copier sous le nom config.local.php dans le même dossier.
 *
 * config.local.php est ignoré par Git : vos identifiants SMTP y sont en
 * sécurité et ne partiront jamais sur GitHub. Ce fichier-ci, en revanche,
 * ne contient que des exemples et peut être versionné sans risque.
 *
 * Sur Railway, ce fichier n'existe pas : les valeurs viennent des variables
 * d'environnement définies dans l'interface.
 */

/* --- SMTP (exemple avec Brevo) ---------------------------------------- */
putenv('SMTP_HOST=smtp-relay.brevo.com');
putenv('SMTP_PORT=587');
putenv('SMTP_USER=votre-identifiant-smtp');
putenv('SMTP_PASS=votre-cle-smtp');
putenv('SMTP_FROM=contact@votre-salon.fr');

/* --- Coordonnées du salon (facultatif en local) ----------------------- */
// putenv('SALON_NOM=Salon Éclat');
// putenv('SALON_EMAIL=contact@votre-salon.fr');
