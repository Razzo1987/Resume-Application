<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Pagina iniziale provvisoria (M0). In M2 "/" reindirizzerà alla lingua di default.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('theme::home');
    }
}
