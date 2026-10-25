<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Site\SiteContext;
use App\Support\Languages\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lingua dal primo segmento dell'URL. Accetta solo le lingue attive; le varianti di maiuscole
 * ("/it-it/...") vengono reindirizzate in modo permanente al codice corretto ("/it-IT/...").
 */
class ResolveSiteLocale
{
    public function __construct(
        private readonly Locales $locales,
        private readonly SiteContext $site,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requested = (string) $request->route('locale');
        $language = $this->locales->match($requested);

        abort_if($language === null, 404);

        if ($language->code !== $requested) {
            $rest = substr($request->getPathInfo(), strlen($requested) + 1);
            $query = $request->getQueryString();

            return redirect()->to(url('/'.$language->code.$rest).($query !== null ? '?'.$query : ''), 301);
        }

        $this->site->enter($language);

        return $next($request);
    }
}
