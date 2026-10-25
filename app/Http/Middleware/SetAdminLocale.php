<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lingua dell'interfaccia admin (traduzioni Filament: "it", "en", ...),
 * indipendente dalle lingue dei contenuti del sito.
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale((string) config('resume.admin_locale', 'it'));

        return $next($request);
    }
}
