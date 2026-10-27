<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Redirect;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Redirects managed from the admin and created automatically when a slug or a URL segment changes.
 * A redirect applies to the given path and to all the paths below it
 * (/it-IT/progetti → /it-IT/lavori also moves /it-IT/progetti/slug).
 */
final class Redirects
{
    /**
     * Target for a path, or null.
     *
     * @return array{0: string, 1: int}|null [target, HTTP status code]
     */
    public function match(string $path): ?array
    {
        // The decoded path is compared (redirects are written "in plain text", spaces included)
        $path = Redirect::normalizePath(rawurldecode($path));

        // Candidates: the path itself and all its "parents" (/a/b/c, /a/b, /a)
        $segments = explode('/', trim($path, '/'));
        $candidates = [];

        for ($length = count($segments); $length >= 1; $length--) {
            $candidates[] = '/'.implode('/', array_slice($segments, 0, $length));
        }

        try {
            if (! Schema::hasTable('redirects')) {
                return null;
            }

            $redirect = Redirect::query()
                ->where('is_active', true)
                ->whereIn('from_path', $candidates)
                ->get()
                ->sortByDesc(fn (Redirect $redirect): int => strlen($redirect->from_path))
                ->first();
        } catch (Throwable) {
            return null;
        }

        if ($redirect === null) {
            return null;
        }

        $redirect->newQuery()->whereKey($redirect->getKey())->update([
            'hits' => $redirect->hits + 1,
            'last_hit_at' => now(),
        ]);

        $target = $redirect->to_path;

        // Redirect of a prefix: the rest of the path is kept
        if ($redirect->from_path !== $path && ! str_starts_with($target, 'http')) {
            $rest = substr($path, strlen($redirect->from_path));
            $target = rtrim($target, '/').implode('/', array_map('rawurlencode', explode('/', $rest)));
        }

        return [$target, $redirect->status_code];
    }

    /**
     * Records a permanent redirect, avoiding chains and loops:
     * redirects that pointed to $from now point to $to; an old redirect from $to is removed.
     */
    public function remember(string $from, string $to): void
    {
        $from = Redirect::normalizePath($from);
        $to = Redirect::normalizePath($to);

        if ($from === $to) {
            return;
        }

        Redirect::query()->where('from_path', $to)->delete();
        Redirect::query()->where('to_path', $from)->update(['to_path' => $to]);

        Redirect::query()->updateOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => 301, 'is_active' => true]);
    }
}
