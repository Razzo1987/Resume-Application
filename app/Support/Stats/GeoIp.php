<?php

declare(strict_types=1);

namespace App\Support\Stats;

use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Provenienza delle visite dai database GeoLite2 di MaxMind (City e ASN), installati in core/storage: l'IP non
 * viene mandato a nessun servizio esterno. I database si scaricano con un account MaxMind gratuito (Account ID e
 * License Key, salvati cifrati) e si aggiornano col cron o dall'admin (GeoIpUpdater).
 */
class GeoIp
{
    public const string CITY = 'GeoLite2-City';

    public const string ASN = 'GeoLite2-ASN';

    public const array EDITIONS = [self::CITY, self::ASN];

    /** @var array<string, Reader|false> */
    private array $readers = [];

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
        return is_file($this->path(self::CITY));
    }

    /**
     * Provenienza di un indirizzo IP (campi null se non si sa).
     *
     * @return array{country: ?string, region: ?string, city: ?string, latitude: ?float, longitude: ?float, asn: ?int, as_org: ?string}
     */
    public function lookup(string $ip): array
    {
        $result = ['country' => null, 'region' => null, 'city' => null, 'latitude' => null, 'longitude' => null, 'asn' => null, 'as_org' => null];

        if (($reader = $this->reader(self::CITY)) !== null) {
            try {
                $record = $reader->city($ip);
                $result['country'] = $record->country->isoCode;
                $result['region'] = $record->mostSpecificSubdivision->name;
                $result['city'] = $record->city->name;
                $result['latitude'] = $record->location->latitude;
                $result['longitude'] = $record->location->longitude;
            } catch (Throwable) {
                // indirizzo non presente (reti private, indirizzi nuovi)
            }
        }

        if (($reader = $this->reader(self::ASN)) !== null) {
            try {
                $record = $reader->asn($ip);
                $result['asn'] = $record->autonomousSystemNumber;
                $result['as_org'] = $record->autonomousSystemOrganization;
            } catch (Throwable) {
                // come sopra
            }
        }

        return $result;
    }

    /**
     * Data di creazione di un database installato (dai suoi metadati).
     */
    public function builtAt(string $edition): ?int
    {
        $reader = $this->reader($edition);

        return $reader !== null ? $reader->metadata()->buildEpoch : null;
    }

    /**
     * Dopo un aggiornamento i file sono cambiati: si riaprono alla prossima ricerca.
     */
    public function forgetReaders(): void
    {
        foreach ($this->readers as $reader) {
            if ($reader instanceof Reader) {
                $reader->close();
            }
        }

        $this->readers = [];
    }

    // --- Account MaxMind ---------------------------------------------------------------------------

    /**
     * Account ID e License Key: quelli del .env (RESUME_GEOIP_ACCOUNT_ID, RESUME_GEOIP_LICENSE_KEY) hanno la
     * precedenza su quelli inseriti nell'admin.
     *
     * @return array{account: string, key: string}|null
     */
    public function credentials(): ?array
    {
        $account = (string) config('resume.geoip.account_id');
        $key = (string) config('resume.geoip.license_key');

        if ($account !== '' && $key !== '') {
            return ['account' => $account, 'key' => $key];
        }

        try {
            $saved = is_file($this->credentialsFile())
                ? json_decode(Crypt::decryptString((string) file_get_contents($this->credentialsFile())), true)
                : null;
        } catch (Throwable) {
            $saved = null; // APP_KEY cambiata: le credenziali vanno reinserite
        }

        return is_array($saved) && is_string($saved['account'] ?? null) && is_string($saved['key'] ?? null)
            ? ['account' => $saved['account'], 'key' => $saved['key']]
            : null;
    }

    /**
     * Da dove vengono le credenziali: "env", "admin" o null.
     */
    public function credentialsSource(): ?string
    {
        return match (true) {
            (string) config('resume.geoip.account_id') !== '' && (string) config('resume.geoip.license_key') !== '' => 'env',
            $this->credentials() !== null => 'admin',
            default => null,
        };
    }

    public function setCredentials(?string $account, ?string $key): void
    {
        if ($account === null || trim($account) === '' || $key === null || trim($key) === '') {
            File::delete($this->credentialsFile());

            return;
        }

        File::ensureDirectoryExists($this->directory());
        file_put_contents($this->credentialsFile(), Crypt::encryptString((string) json_encode(['account' => trim($account), 'key' => trim($key)])));
        @chmod($this->credentialsFile(), 0600);
    }

    public function credentialsFile(): string
    {
        return $this->directory().'/credentials';
    }

    private function reader(string $edition): ?Reader
    {
        if (! array_key_exists($edition, $this->readers)) {
            try {
                $this->readers[$edition] = is_file($this->path($edition)) ? new Reader($this->path($edition), ['en']) : false;
            } catch (Throwable) {
                $this->readers[$edition] = false;
            }
        }

        return $this->readers[$edition] ?: null;
    }
}
