<?php

declare(strict_types=1);

namespace App\Support\Stats;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use MaxMind\Db\Reader as DbReader;
use RuntimeException;
use Throwable;

/**
 * Download e aggiornamento dei database GeoLite2 (City e ASN), come fa geoipupdate:
 *  - controllo con una richiesta HEAD e la data Last-Modified (non conta nei limiti di download di MaxMind);
 *  - download a blocchi da 8 MB (dall'admin ogni blocco è una richiesta breve; dal cron tutto di fila),
 *    autenticato con Account ID e License Key (HTTP Basic), seguendo il rimando di MaxMind allo storage dei file;
 *  - estrazione del .mmdb dal .tar.gz, verifica che si apra e sostituzione del vecchio file solo alla fine.
 *
 * Documentazione: https://dev.maxmind.com/geoip/updating-databases/
 * Le condizioni d'uso di GeoLite chiedono di tenere i database aggiornati: il cron controlla ogni giorno.
 */
class GeoIpUpdater
{
    public const int CHUNK = 8 * 1024 * 1024;

    /**
     * @param  int  $chunk  byte per richiesta (più piccolo nei test)
     */
    public function __construct(
        private readonly GeoIp $geoIp,
        private readonly int $chunk = self::CHUNK,
    ) {}

    /**
     * Stato di ogni database: installato, data della versione installata e di quella disponibile.
     *
     * @return array<string, array{installed: bool, installed_at: ?Carbon, remote_at: ?Carbon, checked_at: ?Carbon, update: bool, size: int}>
     */
    public function status(): array
    {
        $meta = $this->meta();
        $status = [];

        foreach (GeoIp::EDITIONS as $edition) {
            $data = is_array($meta[$edition] ?? null) ? $meta[$edition] : [];
            $installed = is_file($this->geoIp->path($edition));
            $installedAt = is_string($data['installed'] ?? null) ? Carbon::parse($data['installed']) : null;
            $remoteAt = is_string($data['remote'] ?? null) ? Carbon::parse($data['remote']) : null;

            $status[$edition] = [
                'installed' => $installed,
                'installed_at' => $installedAt,
                'remote_at' => $remoteAt,
                'checked_at' => is_string($data['checked'] ?? null) ? Carbon::parse($data['checked']) : null,
                'update' => ! $installed || ($remoteAt !== null && ($installedAt === null || $remoteAt->gt($installedAt))),
                'size' => (int) ($data['size'] ?? 0),
            ];
        }

        return $status;
    }

    public function needsUpdate(): bool
    {
        return collect($this->status())->contains(fn (array $edition): bool => $edition['update']);
    }

    /**
     * Chiede a MaxMind la data dell'ultima versione di ogni database (richieste HEAD).
     *
     * @return array<string, array{installed: bool, installed_at: ?Carbon, remote_at: ?Carbon, checked_at: ?Carbon, update: bool, size: int}>
     */
    public function check(): array
    {
        foreach (GeoIp::EDITIONS as $edition) {
            $response = $this->send(fn (PendingRequest $request): Response => $request->head($this->url($edition)));

            if (! $response->successful()) {
                throw new RuntimeException($this->describe($response));
            }

            $modified = $response->header('Last-Modified');

            $this->updateMeta($edition, [
                'remote' => $modified !== '' ? Carbon::parse($modified)->toAtomString() : null,
                'size' => (int) $response->header('Content-Length'),
                'checked' => now()->toAtomString(),
            ]);
        }

        return $this->status();
    }

    /**
     * Scarica il blocco successivo di un database; a download finito lo installa.
     *
     * @return array{received: int, total: int, done: bool}
     */
    public function next(string $edition): array
    {
        if (! in_array($edition, GeoIp::EDITIONS, true)) {
            throw new RuntimeException('Unknown edition '.$edition);
        }

        $total = $this->status()[$edition]['size'];

        if ($total <= 0) {
            $this->check();
            $total = $this->status()[$edition]['size'];
        }

        $part = $this->geoIp->directory().'/'.$edition.'.tar.gz.part';
        File::ensureDirectoryExists(dirname($part));
        clearstatcache(true, $part);
        $received = is_file($part) ? (int) filesize($part) : 0;

        if ($received > $total) {
            File::delete($part);
            $received = 0;
        }

        if ($received < $total) {
            $end = min($received + $this->chunk, $total) - 1;
            $response = $this->send(fn (PendingRequest $request): Response => $request
                ->withHeaders(['Range' => "bytes={$received}-{$end}"])
                ->get($this->url($edition)));

            if ($response->status() === 206) {
                file_put_contents($part, $response->body(), FILE_APPEND);
            } elseif ($response->status() === 200) {
                file_put_contents($part, $response->body());
            } else {
                throw new RuntimeException($this->describe($response));
            }

            clearstatcache(true, $part);
            $received = (int) filesize($part);
        }

        if ($received < $total) {
            return ['received' => $received, 'total' => $total, 'done' => false];
        }

        try {
            $this->install($edition, $part);
        } finally {
            File::delete($part);
        }

        return ['received' => $total, 'total' => $total, 'done' => true];
    }

    /**
     * Dal cron: controlla e scarica tutto ciò che è da aggiornare.
     */
    public function updateAll(): string
    {
        if ($this->geoIp->credentials() === null) {
            return 'no MaxMind account';
        }

        $this->check();
        $updated = [];

        foreach ($this->status() as $edition => $status) {
            if (! $status['update']) {
                continue;
            }

            @set_time_limit(0);

            do {
                $result = $this->next($edition);
            } while (! $result['done']);

            $updated[] = $edition;
        }

        return $updated === [] ? 'up to date' : 'updated '.implode(', ', $updated);
    }

    /**
     * Estrae il .mmdb dall'archivio, controlla che si apra e sostituisce quello installato.
     */
    public function install(string $edition, string $archive): void
    {
        $target = $this->geoIp->path($edition);
        $temporary = $target.'.tmp';

        if (! $this->extract($archive, $temporary)) {
            File::delete($temporary);

            throw new RuntimeException((string) __('admin.stats.geoip.errors.archive'));
        }

        try {
            $reader = new DbReader($temporary);
            $type = $reader->metadata()->databaseType;
            $reader->close();
        } catch (Throwable) {
            $type = null;
        }

        if ($type === null || ! str_contains($type, $edition === GeoIp::ASN ? 'ASN' : 'City')) {
            File::delete($temporary);

            throw new RuntimeException((string) __('admin.stats.geoip.errors.archive'));
        }

        $this->geoIp->forgetReaders();
        rename($temporary, $target);
        $remote = $this->status()[$edition]['remote_at'];
        $this->updateMeta($edition, ['installed' => ($remote ?? now())->toAtomString()]);
    }

    public function deleteAll(): void
    {
        $this->geoIp->forgetReaders();

        foreach (GeoIp::EDITIONS as $edition) {
            File::delete([$this->geoIp->path($edition), $this->geoIp->directory().'/'.$edition.'.tar.gz.part']);
        }

        File::delete($this->metaFile());
    }

    /**
     * Copia il primo file .mmdb di un .tar.gz (lettura a blocchi, senza estensioni particolari).
     */
    private function extract(string $archive, string $target): bool
    {
        $gz = @gzopen($archive, 'rb');

        if ($gz === false) {
            return false;
        }

        try {
            $longName = null;

            while (! gzeof($gz)) {
                $header = (string) gzread($gz, 512);

                if (strlen($header) < 512 || trim($header, "\0") === '') {
                    return false;
                }

                $name = $longName ?? rtrim(substr($header, 0, 100), "\0");
                $prefix = rtrim(substr($header, 345, 155), "\0");
                $longName = null;
                $name = $prefix !== '' && ! str_contains($name, '/') ? $prefix.'/'.$name : $name;
                $size = (int) octdec(trim(substr($header, 124, 12), "\0 "));
                $type = $header[156];
                $padding = (512 - $size % 512) % 512;

                if ($type === 'L') { // nome lungo GNU: il nome vero è nel blocco dati
                    $longName = rtrim((string) gzread($gz, $size + $padding), "\0");

                    continue;
                }

                if (($type === '0' || $type === "\0") && str_ends_with($name, '.mmdb')) {
                    $output = fopen($target, 'wb');

                    if ($output === false) {
                        return false;
                    }

                    $left = $size;

                    while ($left > 0) {
                        $data = (string) gzread($gz, min(1024 * 1024, $left));

                        if ($data === '') {
                            break;
                        }

                        fwrite($output, $data);
                        $left -= strlen($data);
                    }

                    fclose($output);

                    return $left === 0;
                }

                $skip = $size + $padding;

                while ($skip > 0) {
                    $read = strlen((string) gzread($gz, min(1024 * 1024, $skip)));

                    if ($read === 0) {
                        return false;
                    }

                    $skip -= $read;
                }
            }

            return false;
        } finally {
            gzclose($gz);
        }
    }

    private function url(string $edition): string
    {
        return str_replace('{edition}', $edition, (string) config('resume.geoip.download_url'));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        $credentials = $this->geoIp->credentials() ?? throw new RuntimeException((string) __('admin.stats.geoip.errors.no_account'));

        try {
            return $call(Http::timeout(60)->connectTimeout(8)->withBasicAuth($credentials['account'], $credentials['key']));
        } catch (ConnectionException) {
            throw new RuntimeException((string) __('admin.stats.geoip.errors.unreachable'));
        }
    }

    private function describe(Response $response): string
    {
        return match ($response->status()) {
            401 => (string) __('admin.stats.geoip.errors.unauthorized'),
            429 => (string) __('admin.stats.geoip.errors.rate_limited'),
            default => (string) __('admin.stats.geoip.errors.http', ['status' => $response->status()]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(): array
    {
        $data = is_file($this->metaFile()) ? json_decode((string) file_get_contents($this->metaFile()), true) : null;

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function updateMeta(string $edition, array $values): void
    {
        $meta = $this->meta();
        $meta[$edition] = [...(is_array($meta[$edition] ?? null) ? $meta[$edition] : []), ...$values];
        File::ensureDirectoryExists($this->geoIp->directory());
        file_put_contents($this->metaFile().'.tmp', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        rename($this->metaFile().'.tmp', $this->metaFile());
    }

    private function metaFile(): string
    {
        return $this->geoIp->directory().'/meta.json';
    }
}
