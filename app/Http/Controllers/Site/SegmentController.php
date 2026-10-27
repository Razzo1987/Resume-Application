<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CvDocument;
use App\Site\CvData;
use App\Site\CvFiles;
use App\Site\Pages;
use App\Site\PrivacyPolicy;
use App\Site\SiteContext;
use App\Site\SiteUrls;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Indirizzi "/{lingua}/{segmento}": download del CV, blog, informativa privacy; per progetti ed esperienze
 * (che non hanno una pagina elenco) rimanda alla sezione del CV.
 */
class SegmentController extends Controller
{
    public function __invoke(Request $request, string $locale, string $segment, SiteContext $site, SiteUrls $urls, CvFiles $files): RedirectResponse|BinaryFileResponse|View
    {
        $key = $urls->segmentKey($locale, $segment);
        $canonical = $key !== null ? '/'.$locale.'/'.$urls->segment($locale, $key) : null;

        abort_if($key === null, 404);

        if ($canonical !== $request->getPathInfo()) {
            $query = $request->getQueryString();

            return redirect()->to(url($canonical).($query !== null ? '?'.$query : ''), 301);
        }

        return match ($key) {
            'cv' => $this->downloadCv($request, $site, $files),
            'projects', 'experience' => redirect()->to(url($urls->sectionPath($locale, $key)), 301),
            'blog' => app()->call([app(BlogController::class), 'index'], ['request' => $request, 'locale' => $locale]),
            'privacy' => $this->privacy($locale),
            default => abort(404),
        };
    }

    /**
     * Informativa privacy (attiva dalle impostazioni o con le statistiche).
     */
    private function privacy(string $locale): View
    {
        $policy = app(PrivacyPolicy::class);

        abort_unless($policy->isEnabled(), 404);

        return view('theme::privacy', [
            'page' => app(Pages::class)->privacy($locale),
            'policy' => $policy->render($locale, app(CvData::class)->profile()),
        ]);
    }

    private function downloadCv(Request $request, SiteContext $site, CvFiles $files): BinaryFileResponse
    {
        $document = $files->for($site->language());
        $disk = Storage::disk(CvDocument::DISK);

        abort_if($document === null || ! $disk->exists($document->path), 404);

        $response = new BinaryFileResponse($disk->path($document->path), 200, [
            'Content-Type' => 'application/pdf',
            // Il CV contiene dati di contatto: non serve che finisca nei risultati di ricerca
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'no-cache',
        ], public: false, autoEtag: false, autoLastModified: true);

        $response->setEtag($document->sha256);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $document->downloadName(), self::asciiName($document->downloadName()));
        $response->isNotModified($request);

        return $response;
    }

    private static function asciiName(string $name): string
    {
        $ascii = (string) preg_replace('/[^\x20-\x7E]/', '', Str::ascii($name));

        return str_replace(['%', '/', '\\'], '', $ascii) ?: 'cv.pdf';
    }
}
