<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Site\CvData;
use App\Site\Pages;
use App\Site\SiteContext;
use Illuminate\Contracts\View\View;

/**
 * CV page: the active sections in the order chosen in the admin.
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
