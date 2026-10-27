<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Languages\AdminLocales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lingua dell'interfaccia admin (traduzioni Filament: "it", "en", ...), indipendente dalle lingue dei contenuti
 * del sito: quella scelta dall'amministratore nel profilo, altrimenti RESUME_ADMIN_LOCALE.
 */
class SetAdminLocale
{
    public function __construct(private readonly AdminLocales $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        app()->setLocale($this->locales->for($user instanceof User ? $user : null));

        return $next($request);
    }
}
