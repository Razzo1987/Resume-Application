<?php

declare(strict_types=1);

namespace App\Site\Media;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Immagini caricate dall'admin (disco "uploads", servito dal web server).
 */
final class Images
{
    public const string DISK = 'uploads';

    public function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return url(Storage::disk(self::DISK)->url($path));
    }

    /**
     * Larghezza e altezza (per gli attributi width/height, evitano spostamenti del layout).
     *
     * @return array{0: int, 1: int}|null
     */
    public function size(?string $path): ?array
    {
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            return null;
        }

        $absolute = $disk->path($path);

        /** @var array{0: int, 1: int}|null */
        return Cache::rememberForever('resume.image-size.'.md5($path.'|'.filemtime($absolute)), function () use ($absolute): ?array {
            $info = @getimagesize($absolute);

            return $info === false ? null : [$info[0], $info[1]];
        });
    }

    /**
     * Attributi pronti per un tag <img> (src, width, height), con i valori protetti.
     *
     * @param  array<string, string|int>  $attributes  risultato di attributes()
     */
    public function html(array $attributes): HtmlString
    {
        $parts = [];

        foreach ($attributes as $name => $value) {
            $parts[] = $name.'="'.e((string) $value).'"';
        }

        return new HtmlString(implode(' ', $parts));
    }

    /**
     * Attributi src, width e height per un tag <img>.
     *
     * @return array<string, string|int>
     */
    public function attributes(?string $path): array
    {
        $url = $this->url($path);

        if ($url === null) {
            return [];
        }

        $size = $this->size($path);

        return $size === null ? ['src' => $url] : ['src' => $url, 'width' => $size[0], 'height' => $size[1]];
    }
}
