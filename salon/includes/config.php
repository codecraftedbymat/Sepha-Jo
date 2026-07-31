<?php
/**
 * Configuration du salon.
 * C'est le seul fichier à modifier pour adapter le site à votre activité.
 */

/* --- Identité --------------------------------------------------------- */
define('SALON_NOM',     'Salon Éclat');
define('SALON_EMAIL',   'contact@salon-eclat.fr');   // reçoit les notifications
define('SALON_TEL',     '02 98 00 00 00');
define('SALON_ADRESSE', '12 rue des Lilas, 29200 Brest');
define('SALON_URL',     'http://localhost/salon/public');

/* --- Horaires d'ouverture --------------------------------------------
   Clé = jour de la semaine (0 = dimanche … 6 = samedi)
   Valeur = [heure d'ouverture, heure de fermeture] ou null si fermé.
   Pour une coupure méridienne, voir PAUSE_DEJEUNER plus bas.
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
const PAUSE_DEJEUNER = [12, 13];   // [début, fin] en heures

/* --- Règles de réservation -------------------------------------------- */
const PAS_CRENEAU      = 15;   // granularité des créneaux proposés, en minutes
const DELAI_MIN_HEURES = 2;    // délai minimum entre maintenant et le rendez-vous
const JOURS_AHEAD      = 30;   // horizon de réservation, en jours

/* --- Fermetures exceptionnelles (congés, jours fériés) ---------------- */
const FERMETURES = [
    // '2026-08-15',
    // '2026-12-25',
];

/* --- Notifications ----------------------------------------------------
   Webhook facultatif (Zapier, Make, Google Apps Script…) appelé à chaque
   réservation, pour créer l'événement dans un agenda en ligne.
   Laissez la chaîne vide pour désactiver.
   --------------------------------------------------------------------- */
define('WEBHOOK_AGENDA', '');

date_default_timezone_set('Europe/Paris');
