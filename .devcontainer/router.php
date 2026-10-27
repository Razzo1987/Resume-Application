<?php

// Router del server PHP nel Codespace.
// L'inoltro delle porte di Codespaces chiama il server come "localhost:8000" e mette l'indirizzo pubblico
// negli header X-Forwarded-*: senza questo, gli indirizzi presi dalla richiesta (es. il ritorno alla pagina
// richiesta dopo il login) porterebbero a http://localhost:8000.
if (! empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $host = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
    $https = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0])) === 'https';

    $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = $host;
    $_SERVER['SERVER_PORT'] = $https ? '443' : '80';
    if ($https) {
        $_SERVER['HTTPS'] = 'on';
    }
}

// Poi come "php artisan serve": file statici serviti da PHP, tutto il resto a public/index.php
return require dirname(__DIR__).'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
