<?php

declare(strict_types=1);

namespace App\Support\System;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifica dall'esterno che la cartella core/ non sia raggiungibile dal browser:
 * chiede al sito stesso core/VERSION e si aspetta 403 o 404.
 */
class CoreProbe
{
    public const string PROTECTED = 'protected';

    public const string EXPOSED = 'exposed';

    public const string NOT_APPLICABLE = 'not_applicable';

    public const string UNKNOWN = 'unknown';

    public function check(?string $baseUrl = null): string
    {
        // Layout di sviluppo: il codice è fuori dalla webroot
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
     * Pacchetto di rilascio: il core sta in una cartella "core" dentro la webroot.
     */
    public function isReleaseLayout(): bool
    {
        return basename(base_path()) === 'core' && str_starts_with(base_path(), public_path());
    }
}
