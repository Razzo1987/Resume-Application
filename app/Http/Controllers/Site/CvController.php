<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Site\CvData;
use App\Site\Pages;
use App\Site\SiteContext;
use Illuminate\Contracts\View\View;

/**
 * Pagina del CV: le sezioni attive nell'ordine scelto in admin.
 */
class CvController extends Controller
{
    public function __invoke(SiteContext $site, CvData $cv, Pages $pages): View
    {
        return view('theme::cv', [
            'page' => $pages->cv($site->locale()),
            'cv' => $cv,
        ]);
    }
}
