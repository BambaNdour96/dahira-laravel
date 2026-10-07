<?php

/*
 * Messages de validation en français, pour les règles qui ne reçoivent pas
 * de message personnalisé. Les autres messages restent définis dans
 * App\Support\MessagesValidation.
 */
return [
    'min' => [
        'string' => 'Ce champ doit contenir au moins :min caractères.',
    ],
    'password' => [
        'letters' => 'Le mot de passe doit contenir au moins une lettre.',
        'mixed' => 'Le mot de passe doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
        'symbols' => 'Le mot de passe doit contenir au moins un symbole.',
        'uncompromised' => 'Ce mot de passe est apparu dans une fuite de données sur internet. Choisissez-en un autre.',
    ],
];
