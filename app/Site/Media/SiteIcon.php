<?php

declare(strict_types=1);

namespace App\Site\Media;

use App\Support\Settings\Settings;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Site icon (favicon): from the image uploaded in the admin (square PNG), the 32×32 (browser tab) and
 * 180×180 (iPhone/iPad Home Screen) versions are generated the first time they are needed.
 */
final class SiteIcon
{
    /** @var list<int> */
    public const array SIZES = [32, 180];

    public function __construct(
        private readonly Settings $settings,
        private readonly Images $images,
    ) {}

    /**
     * @return array<int, string>|null size => URL
     */
    public function urls(): ?array
    {
        $path = $this->settings->get('site.icon');
        $disk = Storage::disk('uploads');

        if (! is_string($path) || $path === '' || ! $disk->exists($path)) {
            return null;
        }

        $urls = [];

        foreach (self::SIZES as $size) {
            $variant = (string) preg_replace('/(\.[a-z0-9]+)?$/i', "-{$size}.png", $path, 1);

            if (! $disk->exists($variant) && ! $this->resize($disk->path($path), $disk->path($variant), $size)) {
                return null;
            }

            $urls[$size] = (string) $this->images->url($variant);
        }

        return $urls;
    }

    private function resize(string $source, string $target, int $size): bool
    {
        $image = @imagecreatefromstring((string) @file_get_contents($source));

        if (! $image instanceof GdImage) {
            return false;
        }

        $icon = imagecreatetruecolor($size, $size);

        if ($icon === false) {
            return false;
        }

        imagealphablending($icon, false);
        imagesavealpha($icon, true);
        imagefill($icon, 0, 0, (int) imagecolorallocatealpha($icon, 0, 0, 0, 127));

        // Square center crop, then resize
        $side = min(imagesx($image), imagesy($image));
        imagecopyresampled($icon, $image, 0, 0, intdiv(imagesx($image) - $side, 2), intdiv(imagesy($image) - $side, 2), $size, $size, $side, $side);

        return imagepng($icon, $target, 9);
    }
}
