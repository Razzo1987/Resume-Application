<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Languages\AdminLocales;
use App\Support\Settings\Settings;
use Closure;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Language of the admin interface (Filament translations: "it", "en", ...), independent of the site content languages:
 * the one chosen by the administrator in the profile, otherwise RESUME_ADMIN_LOCALE. Also sets the time zone of the
 * dates shown in the admin (the one of the site).
 */
class SetAdminLocale
{
    public function __construct(
        private readonly AdminLocales $locales,
        private readonly Settings $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        app()->setLocale($this->locales->for($user instanceof User ? $user : null));

        // Dates and times in the admin (tables, forms, pickers) in the time zone of the site; stored in UTC
        FilamentTimezone::set($this->settings->timezone());

        return $next($request);
    }
}
