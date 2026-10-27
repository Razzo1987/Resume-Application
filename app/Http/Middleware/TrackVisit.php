<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Stats\Tracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Visit statistics: the recording happens after the response is sent (terminate), also for the pages
 * served from the HTML cache, so it does not slow down the site. No cookies: see App\Support\Stats\Tracker.
 * The tracker is built only there and any error is swallowed: the statistics never break a page.
 */
class TrackVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        rescue(function () use ($request, $response): void {
            $tracker = app(Tracker::class);

            if ($tracker->shouldTrack($request, $response)) {
                $tracker->record($request);
            }
        }, report: false);
    }
}
