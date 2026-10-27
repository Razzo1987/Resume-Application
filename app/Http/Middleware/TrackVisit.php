<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Stats\Tracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Statistiche delle visite: la registrazione avviene dopo aver mandato la risposta (terminate), anche per le
 * pagine servite dalla cache HTML, quindi non rallenta il sito. Niente cookie: vedi App\Support\Stats\Tracker.
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
