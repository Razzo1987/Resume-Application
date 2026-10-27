<?php

declare(strict_types=1);

namespace App\Support\System;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Checks from the outside that the core/ folder cannot be reached from the browser:
 * requests core/VERSION from the site itself and expects 403 or 404.
 */
class CoreProbe
{
    public const string PROTECTED = 'protected';

    public const string EXPOSED = 'exposed';

    public const string NOT_APPLICABLE = 'not_applicable';

    public const string UNKNOWN = 'unknown';

    public function check(?string $baseUrl = null): string
    {
        // Development layout: the code is outside the web root
        if (! $this->isReleaseLayout()) {
            return self::NOT_APPLICABLE;
        }

        $url = rtrim($baseUrl ?? (string) config('app.url'), '/').'/core/VERSION';

        try {
            $status = Http::timeout(5)->withoutRedirecting()->get($url)->status();
        } catch (Throwable) {
            return self::UNKNOWN;
        }

        return match (true) {
            in_array($status, [401, 403, 404], true) => self::PROTECTED,
            $status === 200 => self::EXPOSED,
            default => self::UNKNOWN,
        };
    }

    /**
     * Release package: the core lives in a "core" folder inside the web root.
     */
    public function isReleaseLayout(): bool
    {
        return basename(base_path()) === 'core' && str_starts_with(base_path(), public_path());
    }
}
