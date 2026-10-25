<?php

declare(strict_types=1);

namespace App\Support\Update;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Scarica il pacchetto di una release da GitHub direttamente sul server, a blocchi di 8 MB (ogni blocco è una
 * richiesta breve, sotto i limiti di tempo degli hosting; un download interrotto riprende da dove era arrivato).
 * Alla fine controlla lo SHA-256 pubblicato con la release.
 */
class ReleaseDownloader
{
    public const int CHUNK = 8 * 1024 * 1024;

    /**
     * @param  int  $chunk  byte per richiesta (più piccolo nei test)
     */
    public function __construct(
        private readonly UpdateStorage $storage,
        private readonly ReleaseChecker $checker,
        private readonly int $chunk = self::CHUNK,
    ) {}

    /**
     * Scarica il blocco successivo.
     *
     * @return array{received: int, total: int, done: bool, file: ?string}
     */
    public function next(Release $release): array
    {
        $asset = $release->package ?? throw new RuntimeException((string) __('admin.updates.errors.no_package', ['version' => $release->version]));
        $final = $this->storage->packagesDirectory().'/'.$release->packageName();
        $part = $final.'.part';
        $total = $asset['size'];

        if (is_file($final)) {
            return ['received' => $total, 'total' => $total, 'done' => true, 'file' => basename($final)];
        }

        File::ensureDirectoryExists(dirname($part));
        clearstatcache(true, $part);
        $received = is_file($part) ? (int) filesize($part) : 0;

        if ($received > $total) {
            File::delete($part);
            $received = 0;
        }

        if ($received < $total) {
            $end = min($received + $this->chunk, $total) - 1;
            $response = $this->fetch($asset, ['Range' => "bytes={$received}-{$end}"]);

            if ($response->status() === 206) {
                file_put_contents($part, $response->body(), FILE_APPEND);
            } elseif ($response->status() === 200) {
                // Il server ha ignorato il blocco richiesto e ha mandato tutto il file
                file_put_contents($part, $response->body());
            } else {
                throw new RuntimeException($this->checker->describe($response));
            }

            clearstatcache(true, $part);
            $received = (int) filesize($part);
        }

        if ($received < $total) {
            return ['received' => $received, 'total' => $total, 'done' => false, 'file' => null];
        }

        $this->verify($release, $part);
        rename($part, $final);

        return ['received' => $total, 'total' => $total, 'done' => true, 'file' => basename($final)];
    }

    /**
     * Lo zip deve corrispondere allo SHA-256 pubblicato con la release (se c'è).
     */
    private function verify(Release $release, string $file): void
    {
        if ($release->checksum === null) {
            return;
        }

        $response = $this->fetch($release->checksum);

        if (! $response->successful() || preg_match('/^([a-f0-9]{64})\b/i', trim($response->body()), $match) !== 1) {
            throw new RuntimeException((string) __('admin.updates.errors.checksum_missing'));
        }

        if (! hash_equals(strtolower($match[1]), (string) hash_file('sha256', $file))) {
            File::delete($file);

            throw new RuntimeException((string) __('admin.updates.errors.checksum_mismatch'));
        }
    }

    /**
     * Un file allegato alla release. Con il token (repository privato) si passa dall'API, che rimanda a un indirizzo
     * temporaneo di GitHub (il token non viene inviato a quell'indirizzo); senza token dal link pubblico.
     *
     * @param  array{id: int, name: string, size: int, api_url: string, download_url: string}  $asset
     * @param  array<string, string>  $headers
     */
    private function fetch(array $asset, array $headers = []): Response
    {
        $useApi = $this->storage->token() !== null && $asset['api_url'] !== '';

        try {
            return $this->request()
                ->withHeaders([...$headers, 'Accept' => 'application/octet-stream'])
                ->get($useApi ? $asset['api_url'] : $asset['download_url']);
        } catch (ConnectionException) {
            throw new RuntimeException((string) __('admin.updates.errors.unreachable'));
        }
    }

    private function request(): PendingRequest
    {
        return $this->checker->api()->timeout(60);
    }
}
