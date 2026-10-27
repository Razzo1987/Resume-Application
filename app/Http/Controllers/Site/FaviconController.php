<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Site\Media\SiteIcon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * /favicon.ico (requested by some browsers and crawlers even without <link rel="icon">): redirects to the site icon,
 * or responds 404 without a page (no wasted work).
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
