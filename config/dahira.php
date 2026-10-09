<?php

return [
    'admin' => [
        'nom' => env('ADMIN_NAME', 'Administrateur'),
        'telephone' => env('ADMIN_TELEPHONE'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
    'consentement_version' => env('CONSENT_VERSION', '2026-10'),
    // Mois de démarrage des cotisations mensuelles : personne ne doit rien avant cette date.
    'cotisations_debut' => env('COTISATIONS_DEBUT', '2026-11'),

    // Données personnelles : contact du responsable et hébergeur (à préciser à la mise en ligne).
    'contact_donnees' => env('DAHIRA_CONTACT_DONNEES', 'le super administrateur de la plateforme, via le bureau de la Daara'),
    'hebergeur' => env('DAHIRA_HEBERGEUR', 'précisé lors de la mise en ligne'),

    // Déconnexion après inactivité, en minutes.
    'inactivite_bureau' => (int) env('DAHIRA_INACTIVITE_BUREAU', 10),
    'inactivite_super_admin' => (int) env('DAHIRA_INACTIVITE_SUPER_ADMIN', 25),
    'inactivite_membres' => (int) env('DAHIRA_INACTIVITE_MEMBRES', 2),
    'session_max_membres' => (int) env('DAHIRA_SESSION_MAX_MEMBRES', 10),
];
