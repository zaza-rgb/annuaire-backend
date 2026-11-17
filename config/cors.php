<?php

return [

    // Seules les routes de l'API sont ouvertes aux appels depuis le navigateur.
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // En production : remplacer par l'adresse réelle du site (ex. https://afroponte.com).
    'allowed_origins' => [],

    // Développement : localhost et 127.0.0.1 sur n'importe quel port.
    'allowed_origins_patterns' => ['#^http://(localhost|127\.0\.0\.1):\d+$#'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // Faux : l'API utilise un jeton Bearer, pas de cookies.
    'supports_credentials' => false,

];