<?php

use App\Http\Middleware\CacheSitePages;
use App\Http\Middleware\NormalizeSitePath;
use App\Http\Middleware\RedirectToInstaller;
use App\Http\Middleware\SiteMaintenance;
use App\Http\Middleware\SiteSecurityHeaders;
use App\Http\Middleware\StripCookies;
use App\Http\Middleware\TrackVisit;
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
            // Installer: no session (before installation the app key does not exist)
            Route::middleware('installer')->group(base_path('routes/install.php'));

            // Public site: no session, no CSRF, no cookies.
            Route::middleware('site')->group(base_path('routes/site.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Before installation everything leads to /install
        $middleware->prepend(RedirectToInstaller::class);

        $middleware->group('installer', [
            StripCookies::class,
        ]);

        // Order matters: the response (even a cached one) always goes through the security headers and StripCookies
        $middleware->group('site', [
            TrackVisit::class,
            NormalizeSitePath::class,
            SiteSecurityHeaders::class,
            StripCookies::class,
            SiteMaintenance::class,
            CacheSitePages::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 404 on the public site: redirect from the table, otherwise the theme page
        $exceptions->render(fn (NotFoundHttpException $exception, Request $request) => app(NotFound::class)->render($request));
    })->create();

// Release package: the code is in public_html/core and the webroot is the parent folder.
// This applies both to web requests and to artisan commands.
if (basename(dirname(__DIR__)) === 'core' && is_file(dirname(__DIR__, 2).'/index.php')) {
    $app->usePublicPath(dirname(__DIR__, 2));
}

return $app;
