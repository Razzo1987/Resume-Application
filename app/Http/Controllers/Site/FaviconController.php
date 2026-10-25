<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Site\Media\SiteIcon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * /favicon.ico (chiesto da alcuni browser e crawler anche senza <link rel="icon">): rimanda all'icona del sito,
 * oppure risponde 404 senza pagina (niente lavoro inutile).
 */
class FaviconController extends Controller
{
    public function __invoke(SiteIcon $icon): RedirectResponse|Response
    {
        $urls = $icon->urls();

        return $urls !== null
            ? redirect()->to($urls[32], 301)
            : response('', 404, ['Content-Type' => 'text/plain']);
    }
}
