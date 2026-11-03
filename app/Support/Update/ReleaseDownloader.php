<?php

declare(strict_types=1);

namespace App\Support\Update;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Downloads a release package from GitHub directly to the server, in 8 MB chunks (each chunk is a short request,
 * within hosting time limits; an interrupted download resumes from where it stopped).
 * At the end it checks the SHA-256 and the Ed25519 signature published with the release (Signature).
 */
class ReleaseDownloader
{
    public const int CHUNK = 8 * 1024 * 1024;

    /**
     * @param  int  $chunk  bytes per request (smaller in tests)
     */
    public function __construct(
        private readonly UpdateStorage $storage,
        private readonly ReleaseChecker $checker,
        private readonly GitHub $github,
        private readonly int $chunk = self::CHUNK,
    ) {}

    /**
     * Downloads the next chunk.
     *
     * @return array{received: int, total: int, done: bool, file: ?string}
     */
    public function next(Release $release): array
    {
        $asset = $release->package ?? throw new RuntimeException((string) __('admin.updates.errors.no_package', ['version' => $release->version]));

        // Without the SHA-256 published with the release the package is not downloaded at all
        if ($release->checksum === null) {
            throw new RuntimeException((string) __('admin.updates.errors.checksum_none', ['version' => $release->version]));
        }

        // The same for the signature: a release without it is not downloaded
        if ($release->signature === null) {
            throw new RuntimeException((string) __('admin.updates.errors.signature_none', ['version' => $release->version]));
        }

        $final = $this->storage->packagesDirectory().'/'.$release->packageName();
        $part = $final.'.part';
        $total = $asset['size'];

        if (is_file($final) && is_file($final.'.signed')) {
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
                // The server ignored the requested range and sent the whole file
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
        // Marker for the checks before the installation: this package comes from GitHub with a valid signature
        file_put_contents($final.'.signed', $release->version);

        return ['received' => $total, 'total' => $total, 'done' => true, 'file' => basename($final)];
    }

    /**
     * The zip must match the SHA-256 published with the release (always required).
     */
    private function verify(Release $release, string $file): void
    {
        if ($release->checksum === null) {
            File::delete($file);

            throw new RuntimeException((string) __('admin.updates.errors.checksum_none', ['version' => $release->version]));
        }

        $response = $this->fetch($release->checksum);

        if (! $response->successful() || preg_match('/^([a-f0-9]{64})\b/i', trim($response->body()), $match) !== 1) {
            throw new RuntimeException((string) __('admin.updates.errors.checksum_missing'));
        }

        if (! hash_equals(strtolower($match[1]), (string) hash_file('sha256', $file))) {
            File::delete($file);

            throw new RuntimeException((string) __('admin.updates.errors.checksum_mismatch'));
        }

        $signature = $release->signature !== null ? $this->fetch($release->signature) : null;

        if ($signature === null || ! $signature->successful()) {
            throw new RuntimeException((string) __('admin.updates.errors.signature_missing'));
        }

        if (! Signature::verify($file, $signature->body(), Signature::appKeys())) {
            File::delete($file);

            throw new RuntimeException((string) __('admin.updates.errors.signature_bad'));
        }
    }

    /**
     * A file attached to the release (through the API with the key for a private repository, see GitHub::asset()).
     *
     * @param  array{id: int, name: string, size: int, api_url: string, download_url: string}  $asset
     * @param  array<string, string>  $headers
     */
    private function fetch(array $asset, array $headers = []): Response
    {
        try {
            return $this->github->asset($this->checker->repository(), $asset, $headers);
        } catch (ConnectionException) {
            throw new RuntimeException((string) __('admin.updates.errors.unreachable'));
        }
    }
}
