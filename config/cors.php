<?php

return [

    // Seules les routes de l'API sont ouvertes aux appels depuis le navigateur.
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // En production ou Wasmer : domaines configurables via la variable CORS_ALLOWED_ORIGINS (séparés par des virgules)
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))))),

    // Développement : localhost et 127.0.0.1 sur n'importe quel port, et domaines Wasmer Edge
    'allowed_origins_patterns' => [
        '#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#',
        '#^https://.*\.wasmer\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // Faux : l'API utilise un jeton Bearer, pas de cookies.
    'supports_credentials' => false,

];