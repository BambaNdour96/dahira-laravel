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
];
