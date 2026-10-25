<?php

use App\Http\Middleware\CacheSitePages;
use App\Http\Middleware\NormalizeSitePath;
use App\Http\Middleware\SiteSecurityHeaders;
use App\Http\Middleware\StripCookies;
use App\Site\NotFound;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            // Sito pubblico: nessuna sessione, nessun CSRF, nessun cookie.
            Route::middleware('site')->group(base_path('routes/site.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // L'ordine conta: la risposta (anche quella dalla cache) passa sempre da intestazioni di sicurezza e StripCookies
        $middleware->group('site', [
            NormalizeSitePath::class,
            SiteSecurityHeaders::class,
            StripCookies::class,
            CacheSitePages::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 404 del sito pubblico: redirect dalla tabella, altrimenti la pagina del tema
        $exceptions->render(fn (NotFoundHttpException $exception, Request $request) => app(NotFound::class)->render($request));
    })->create();

// Pacchetto di rilascio: il codice sta in public_html/core e la webroot è la cartella superiore.
// Vale sia per le richieste web sia per i comandi artisan.
if (basename(dirname(__DIR__)) === 'core' && is_file(dirname(__DIR__, 2).'/index.php')) {
    $app->usePublicPath(dirname(__DIR__, 2));
}

return $app;
