<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Site\CvData;
use App\Site\Pages;
use App\Support\System\Maintenance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public site in maintenance: 503 with the theme's page (the admin stays reachable).
 */
class SiteMaintenance
{
    public function __construct(private readonly Maintenance $maintenance) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->maintenance->isOn()) {
            return $next($request);
        }

        $html = view('theme::errors.503', [
            'page' => app(Pages::class)->maintenance(),
            'cv' => app(CvData::class),
        ])->render();

        return response($html, 503, ['Retry-After' => '600', 'Cache-Control' => 'no-store']);
    }
}
