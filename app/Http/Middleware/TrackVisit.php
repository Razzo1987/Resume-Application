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
 */
class TrackVisit
{
    public function __construct(private readonly Tracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->tracker->shouldTrack($request, $response)) {
            rescue(fn () => $this->tracker->record($request), report: false);
        }
    }
}
