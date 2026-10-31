<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Cron\Cron;
use App\Support\Stats\Exclusions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin pages with a logged-in administrator: records the PHP version of the site (for automatic updates) and the
 * administrator's IP and browser (their visits to the site do not count in the statistics, also those made just
 * before signing in) and, if there is no hosting cron, starts the light scheduled jobs after the response is sent.
 */
class AdminActivity
{
    public function __construct(
        private readonly Cron $cron,
        private readonly Exclusions $exclusions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() !== null) {
            rescue(function () use ($request): void {
                $this->cron->rememberWebRuntime();
                $this->exclusions->touchAdmin($request->ip(), (string) $request->userAgent());

                if (! app()->runningUnitTests()) {
                    $this->cron->runFromAdminIfNeeded();
                }
            }, report: false);
        }

        return $response;
    }
}
