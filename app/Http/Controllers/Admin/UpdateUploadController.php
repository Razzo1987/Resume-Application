<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Update\UpdateStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Chunked upload of the update package (System page) or of a theme package (Themes page, ?target=theme): the browser
 * sends the zip in 1 MB pieces, so the hosting's upload limit (upload_max_filesize, often 2 MB) does not matter.
 * Each chunk is the raw request body; the upload resumes from the chunk indicated by the server.
 */
class UpdateUploadController extends Controller
{
    public const int MAX_CHUNKS = 200;

    public function __invoke(Request $request, UpdateStorage $storage): JsonResponse
    {
        $id = (string) $request->query('id');
        $index = (int) $request->query('index', -1);
        $count = (int) $request->query('count', 0);
        $name = (string) $request->query('name');

        if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1 || $index < 0 || $count < 1 || $count > self::MAX_CHUNKS || $index >= $count
            || preg_match('/^[A-Za-z0-9._-]{1,120}\.zip$/', $name) !== 1) {
            return response()->json(['error' => __('admin.updates.errors.upload_invalid')], 422);
        }

        File::ensureDirectoryExists($storage->uploadsDirectory());
        $this->cleanup($storage);

        $part = $storage->uploadsDirectory()."/{$id}.part";
        $meta = $storage->uploadsDirectory()."/{$id}.json";
        $state = $index === 0 ? ['next' => 0] : ($storage->readJson($meta) ?? ['next' => 0]);

        if ($index === 0) {
            File::delete($part);
        }

        if ($index !== (int) $state['next']) {
            return response()->json(['next' => (int) $state['next']], 409);
        }

        file_put_contents($part, $request->getContent(), FILE_APPEND);
        $state['next'] = $index + 1;

        if ($state['next'] < $count) {
            $storage->writeJson($meta, $state);

            return response()->json(['next' => $state['next']]);
        }

        $target = $request->query('target') === 'theme' ? $storage->themeUploadsDirectory() : $storage->packagesDirectory();
        File::ensureDirectoryExists($target);
        rename($part, $target.'/'.$name);
        File::delete($meta);

        return response()->json(['done' => true, 'file' => $name]);
    }

    /**
     * Uploads interrupted more than a day ago.
     */
    private function cleanup(UpdateStorage $storage): void
    {
        foreach (glob($storage->uploadsDirectory().'/*') ?: [] as $file) {
            if (filemtime($file) < time() - 86400) {
                File::delete($file);
            }
        }
    }
}
