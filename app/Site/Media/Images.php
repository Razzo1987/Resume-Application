<?php

declare(strict_types=1);

namespace App\Site\Media;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Images uploaded from the admin ("uploads" disk, served by the web server).
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
     * Width and height (for the width/height attributes, they prevent layout shifts).
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
     * Attributes ready for an <img> tag (src, width, height), with the values escaped.
     *
     * @param  array<string, string|int>  $attributes  result of attributes()
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
     * The src, width and height attributes for an <img> tag.
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
