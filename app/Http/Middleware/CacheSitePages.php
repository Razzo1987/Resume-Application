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
 * HTML cache of the public pages: the second visit does not touch the database.
 * Pages have an ETag, so the browser reloads only if the content has changed.
 */
class CacheSitePages
{
    public function __construct(private readonly PageCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() || $request->getQueryString() !== null || ! $this->isSiteHost($request)) {
            return $next($request);
        }

        $key = $this->cache->key($request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo());
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

        // The browser already has this version
        $response->isNotModified($request);

        return $response;
    }

    /**
     * In production only the requests for the domain of APP_URL are cached: the key contains the host, and requests
     * with invented Host headers must not add files to the cache. They are still answered (links use APP_URL).
     */
    private function isSiteHost(Request $request): bool
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return ! app()->isProduction() || ! is_string($host) || $host === '' || strcasecmp($request->getHost(), $host) === 0;
    }

    private function isCacheableType(string $type): bool
    {
        return str_starts_with($type, 'text/html')
            || str_starts_with($type, 'text/plain')
            || str_starts_with($type, 'application/xml')
            || str_starts_with($type, 'application/rss+xml')
            || str_starts_with($type, 'text/xml');
    }
}
