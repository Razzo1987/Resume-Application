<?php

declare(strict_types=1);

namespace App\Site;

use App\Http\Middleware\NormalizeSitePath;
use App\Http\Middleware\SiteSecurityHeaders;
use App\Support\Languages\Locales;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Pagina non trovata nel sito pubblico: prima si cerca un redirect (tabella redirects),
 * poi si mostra la pagina 404 del tema nella lingua dell'URL (o in quella di default).
 */
final class NotFound
{
    public function __construct(
        private readonly Redirects $redirects,
        private readonly Locales $locales,
        private readonly SiteContext $site,
    ) {}

    public function render(Request $request): ?Response
    {
        if ($this->isOutsideSite($request)) {
            return null;
        }

        // Indirizzi con slash finale o doppi slash che non corrispondono a nessuna rotta
        if (($normalized = NormalizeSitePath::redirect($request)) !== null) {
            return $normalized;
        }

        if ($request->isMethodSafe()) {
            $match = $this->redirects->match($request->getPathInfo());

            if ($match !== null) {
                [$target, $status] = $match;

                return redirect()->to(str_starts_with($target, 'http') ? $target : url($target), $status);
            }
        }

        try {
            $first = explode('/', trim($request->getPathInfo(), '/'))[0];
            $language = $this->locales->match($first) ?? $this->locales->find($this->locales->default());

            if ($language !== null) {
                $this->site->enter($language);
            }

            $html = view('theme::errors.404', [
                'page' => app(Pages::class)->notFound(),
                'cv' => app(CvData::class),
            ])->render();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        return SiteSecurityHeaders::apply($request, response($html, 404));
    }

    /**
     * Admin, Livewire e file statici mantengono la gestione standard di Laravel.
     */
    private function isOutsideSite(Request $request): bool
    {
        $first = explode('/', trim($request->getPathInfo(), '/'))[0];
        $admin = trim((string) config('resume.admin_path'), '/');

        return $request->expectsJson()
            || $first === $admin
            || str_starts_with($first, 'livewire')
            || in_array($first, ['filament', 'storage', 'themes', 'uploads', 'build', 'js', 'css', 'fonts'], true);
    }
}
