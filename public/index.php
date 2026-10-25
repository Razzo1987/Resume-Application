<?php

/**
 * Resume App — front controller.
 *
 * Funziona con due disposizioni:
 *  - pacchetto di rilascio: questo file sta nella webroot (es. public_html/)
 *    e il codice dell'app sta in ./core, protetto da .htaccess;
 *  - sviluppo (layout Laravel standard): questo file sta in public/
 *    e il codice dell'app sta nella cartella superiore.
 *
 * Se l'hosting permette di tenere il codice fuori dalla webroot, crea accanto
 * a questo file un core-path.php (non incluso nei pacchetti, quindi non viene
 * sovrascritto dagli aggiornamenti) con il percorso assoluto del core:
 *
 *     <?php return '/home/utente/resume-core';
 */

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$corePath = match (true) {
    is_file(__DIR__.'/core-path.php') => (string) require __DIR__.'/core-path.php',
    is_dir(__DIR__.'/core') => __DIR__.'/core',
    default => dirname(__DIR__),
};
$corePath = rtrim($corePath, '/\\');

if (! is_file($corePath.'/bootstrap/app.php') || ! is_file($corePath.'/vendor/autoload.php')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Retry-After: 300');
    echo 'Resume App: application core not found. Please check the installation.';
    exit;
}

// Modalità manutenzione (attivata dall'admin durante gli aggiornamenti)
if (file_exists($maintenance = $corePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $corePath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $corePath.'/bootstrap/app.php';

// La webroot è sempre la cartella di questo file
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
