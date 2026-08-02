<?php
/**
 * Configuration du salon.
 *
 * Chaque réglage peut être défini par une variable d'environnement (pratique
 * sur Railway, où l'on modifie les valeurs sans redéployer) ou, à défaut,
 * par la valeur écrite ici — ce qui suffit en local.
 */

/**
 * Lit une variable d'environnement, ou renvoie la valeur par défaut.
 * Selon la configuration du serveur, les variables arrivent via getenv(),
 * $_SERVER ou $_ENV : on interroge les trois.
 */
function env(string $cle, $defaut = null)
{
    foreach ([$_SERVER, $_ENV] as $source) {
        if (isset($source[$cle]) && $source[$cle] !== '') {
            return $source[$cle];
        }
    }

    $v = getenv($cle);
    return ($v === false || $v === '') ? $defaut : $v;
}

/* --- Réglages locaux --------------------------------------------------
   Si includes/config.local.php existe, il est chargé ici. Ce fichier est
   exclu de Git : c'est l'endroit où mettre vos identifiants SMTP pour les
   tests en local, sans risquer de les publier sur GitHub.
   Voir config.local.example.php pour le modèle.
   --------------------------------------------------------------------- */
$fichierLocal = __DIR__ . '/config.local.php';
if (is_file($fichierLocal)) {
    require_once $fichierLocal;
}

/* --- Identité --------------------------------------------------------- */
define('SALON_NOM',     env('SALON_NOM',     'Salon Éclat'));
define('SALON_EMAIL',   env('SALON_EMAIL',   'contact@salon-eclat.fr'));
define('SALON_TEL',     env('SALON_TEL',     '02 98 00 00 00'));
define('SALON_ADRESSE', env('SALON_ADRESSE', '12 rue des Lilas, 29200 Brest'));

/* URL publique du site. Sur Railway, RAILWAY_PUBLIC_DOMAIN est fourni. */
define('SALON_URL', env('SALON_URL',
    env('RAILWAY_PUBLIC_DOMAIN')
        ? 'https://' . env('RAILWAY_PUBLIC_DOMAIN')
        : 'http://localhost/salon/public'
));

/* --- Horaires : VALEURS DE REPLI SEULEMENT ---------------------------
   Les horaires réels, la pause et les fermetures sont désormais stockés
   en base et se modifient depuis l'administration, menu Disponibilités.
   Les constantes ci-dessous ne servent qu'au tout premier démarrage,
   avant l'exécution de setup.php. Les modifier n'a aucun effet une fois
   l'installation faite.
   --------------------------------------------------------------------- */
const HORAIRES = [
    0 => null,        // dimanche
    1 => null,        // lundi
    2 => [9, 19],     // mardi
    3 => [9, 19],     // mercredi
    4 => [9, 19],     // jeudi
    5 => [9, 19],     // vendredi
    6 => [9, 17],     // samedi
];

/* Pause déjeuner appliquée à tous les jours ouvrés, ou null pour aucune. */
const PAUSE_DEJEUNER = [12, 13];

/* --- Règles de réservation -------------------------------------------- */
const PAS_CRENEAU      = 15;   // granularité des créneaux, en minutes
const DELAI_MIN_HEURES = 2;    // délai minimum avant un rendez-vous
const JOURS_AHEAD      = 30;   // horizon de réservation, en jours

/* --- Fermetures exceptionnelles --------------------------------------- */
const FERMETURES = [
    // '2026-08-15',
];

/* --- Envoi des e-mails ------------------------------------------------
   En local comme sur Railway, la fonction mail() de PHP ne fonctionne pas.
   Renseignez un SMTP (Brevo, Mailgun, Gmail…) via les variables
   d'environnement pour que les confirmations partent réellement.
   Si SMTP_HOST est vide, l'envoi est simplement ignoré sans bloquer la
   réservation.
   --------------------------------------------------------------------- */
/* Clé d'API Brevo — voie privilégiée en ligne.
   Beaucoup d'hébergeurs bloquent les ports SMTP sortants ; l'API passe
   par HTTPS et n'est donc jamais bloquée. À créer dans Brevo, onglet
   « Clés API et MCP » (ce n'est PAS la clé SMTP). */
define('BREVO_API_KEY', env('BREVO_API_KEY', ''));

define('SMTP_HOST', env('SMTP_HOST', ''));
define('SMTP_PORT', (int) env('SMTP_PORT', 587));
define('SMTP_USER', env('SMTP_USER', ''));
define('SMTP_PASS', env('SMTP_PASS', ''));
define('SMTP_FROM', env('SMTP_FROM', SALON_EMAIL));

/* --- Webhook agenda (Zapier, Make, Google Apps Script…) --------------- */
define('WEBHOOK_AGENDA', env('WEBHOOK_AGENDA', ''));

date_default_timezone_set(env('TZ', 'Europe/Paris'));

/* --- Contrôle de version PHP -----------------------------------------
   Le projet nécessite PHP 7.4 minimum. PHP 8.1 ou plus est recommandé.
   --------------------------------------------------------------------- */
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('Ce site nécessite PHP 7.4 ou plus récent. Version détectée : ' . PHP_VERSION);
}
