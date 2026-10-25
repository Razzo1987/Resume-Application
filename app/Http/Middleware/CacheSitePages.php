<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Site\Cache\PageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cache HTML delle pagine pubbliche: la seconda visita non tocca il database.
 * Le pagine hanno un ETag, quindi il browser ricarica solo se il contenuto è cambiato.
 */
class CacheSitePages
{
    public function __construct(private readonly PageCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() || $request->getQueryString() !== null) {
            return $next($request);
        }

        $key = 'page.'.sha1($request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo());
        $cached = $this->cache->get($key);

        if ($cached !== null) {
            return $this->finish($request, new Response($cached['content'], 200, ['Content-Type' => $cached['type']]), $cached['etag'], 'HIT');
        }

        $response = $next($request);

        if ($response->getStatusCode() !== 200
            || $response instanceof BinaryFileResponse
            || $response instanceof StreamedResponse
            || ! $this->isCacheableType((string) $response->headers->get('Content-Type'))) {
            return $response;
        }

        $etag = $this->cache->put($key, (string) $response->getContent(), (string) $response->headers->get('Content-Type'));

        return $this->finish($request, $response, $etag, 'MISS');
    }

    private function finish(Request $request, Response $response, string $etag, string $status): Response
    {
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'public, max-age=0, must-revalidate');
        $response->headers->set('X-Page-Cache', $status);

        // Il browser ha già questa versione
        $response->isNotModified($request);

        return $response;
    }

    private function isCacheableType(string $type): bool
    {
        return str_starts_with($type, 'text/html')
            || str_starts_with($type, 'text/plain')
            || str_starts_with($type, 'application/xml')
            || str_starts_with($type, 'text/xml');
    }
}
