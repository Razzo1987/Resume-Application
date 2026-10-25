<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un solo indirizzo per pagina: niente slash finale né doppi slash ("/it-IT/" → "/it-IT").
 * Il blocco .htaccess fa lo stesso su Apache; qui vale anche con altri server.
 */
class NormalizeSitePath
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::redirect($request) ?? $next($request);
    }

    /**
     * Redirect verso l'indirizzo normalizzato, se quello richiesto non lo è.
     */
    public static function redirect(Request $request): ?Response
    {
        $path = $request->getPathInfo();
        $normalized = '/'.trim((string) preg_replace('#/{2,}#', '/', $path), '/');

        if ($normalized === $path || ! $request->isMethodSafe()) {
            return null;
        }

        $query = $request->getQueryString();

        return redirect()->to(url($normalized).($query !== null ? '?'.$query : ''), 301);
    }
}
