<?php

/**
 * Resume App — front controller.
 *
 * It works with two layouts:
 *  - release package: this file is in the webroot (e.g. public_html/)
 *    and the app code is in ./core, protected by .htaccess;
 *  - development (standard Laravel layout): this file is in public/
 *    and the app code is in the parent folder.
 *
 * If the hosting allows keeping the code outside the webroot, create next
 * to this file a core-path.php (not included in the packages, so it is not
 * overwritten by updates) with the absolute path of the core:
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

// Update started from the admin: while the files are being replaced the app does not start
// (chunked extraction and "Sito in aggiornamento" page, see resources/update/updater.php)
if (is_file($corePath.'/storage/framework/resume-update.json') && is_file($corePath.'/storage/framework/resume-updater.php')) {
    require $corePath.'/storage/framework/resume-updater.php';
    resume_updater_handle($corePath.'/storage/framework/resume-update.json', __DIR__, $corePath);
}

if (! is_file($corePath.'/bootstrap/app.php') || ! is_file($corePath.'/vendor/autoload.php')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Retry-After: 300');
    echo 'Resume App: application core not found. Please check the installation.';
    exit;
}

// Maintenance mode (enabled by the admin during updates)
if (file_exists($maintenance = $corePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $corePath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $corePath.'/bootstrap/app.php';

// The webroot is always the folder of this file
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
