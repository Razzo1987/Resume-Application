<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One URL per page: no trailing slash and no double slashes ("/it-IT/" → "/it-IT").
 * The .htaccess block does the same on Apache; this also works with other servers.
 */
class NormalizeSitePath
{
    public function handle(Request $request, Closure $next): Response
    {
        return self::redirect($request) ?? $next($request);
    }

    /**
     * Redirect to the normalized URL, if the requested one is not.
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
