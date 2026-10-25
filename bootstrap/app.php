<?php

use App\Http\Middleware\StripCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Sito pubblico: nessuna sessione, nessun CSRF, nessun cookie.
            Route::middleware('site')->group(base_path('routes/site.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('site', [
            StripCookies::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Pacchetto di rilascio: il codice sta in public_html/core e la webroot è la cartella superiore.
// Vale sia per le richieste web sia per i comandi artisan.
if (basename(dirname(__DIR__)) === 'core' && is_file(dirname(__DIR__, 2).'/index.php')) {
    $app->usePublicPath(dirname(__DIR__, 2));
}

return $app;
