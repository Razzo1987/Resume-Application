<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Site\Security\Csp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Intestazioni di sicurezza delle pagine pubbliche.
 */
class SiteSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($request, $next($request));
    }

    public static function apply(Request $request, Response $response): Response
    {
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), browsing-topics=()');

        // Con il server di sviluppo di Vite gli asset arrivano da un'altra origine: niente CSP
        if (str_starts_with((string) $headers->get('Content-Type'), 'text/html') && ! Vite::isRunningHot()) {
            $headers->set('Content-Security-Policy', Csp::header($request->isSecure()));
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
