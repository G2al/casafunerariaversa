<?php

return [
    'admin' => [
        'name' => env('INITIAL_ADMIN_NAME', 'Amministratore'),
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],
    // Facoltativo: gli altri operatori si possono creare dal pannello.
    'operator' => [
        'name' => env('INITIAL_OPERATOR_NAME', 'Operatore'),
        'email' => env('INITIAL_OPERATOR_EMAIL'),
        'password' => env('INITIAL_OPERATOR_PASSWORD'),
    ],
];
