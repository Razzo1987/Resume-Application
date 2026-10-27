<?php

declare(strict_types=1);

namespace App\Support\Stats;

/**
 * Provenienza delle visite dai database GeoLite2 di MaxMind (City e ASN), installati sul server.
 */
class GeoIp
{
    /**
     * @param  string|null  $storage  cartella storage dell'app (di default quella di Laravel; serve ai test)
     */
    public function __construct(private readonly ?string $storage = null) {}

    public function directory(): string
    {
        return rtrim($this->storage ?? storage_path(), '/').'/app/private/geoip';
    }

    public function path(string $edition): string
    {
        return $this->directory().'/'.$edition.'.mmdb';
    }

    /**
     * Il database delle città c'è: la provenienza delle visite si può ricavare.
     */
    public function isAvailable(): bool
    {
        return is_file($this->path('GeoLite2-City'));
    }
}
