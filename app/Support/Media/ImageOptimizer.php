<?php

declare(strict_types=1);

namespace App\Support\Media;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Resizes uploaded images and saves them as WebP (lighter, supported by all browsers).
 * Animated GIFs and files that cannot be resized are saved as they are.
 */
class ImageOptimizer
{
    public const int QUALITY = 82;

    /**
     * Saves the image on the given disk and returns the relative path.
     */
    public function store(string $sourcePath, string $originalName, string $disk, string $directory, int $maxSize = 1600, string $format = 'webp'): string
    {
        $basename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'image';
        $unique = $basename.'-'.Str::lower(Str::random(6));
        $directory = trim($directory, '/');

        $image = $this->load($sourcePath);

        if ($image === null) {
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'bin';
            $path = ltrim("{$directory}/{$unique}.{$extension}", '/');
            Storage::disk($disk)->put($path, (string) file_get_contents($sourcePath));

            return $path;
        }

        $image = $this->resize($image, $maxSize);

        ob_start();

        if ($format === 'png') {
            // PNG (e.g. the site icon: browsers accept it everywhere), transparency preserved
            imagepng($image, null, 9);
            $extension = 'png';
        } elseif ($format === 'jpeg') {
            // JPEG (e.g. the social sharing image, where WebP is not always supported): transparency on white
            imagejpeg($this->flatten($image), null, 85);
            $extension = 'jpg';
        } else {
            imagewebp($image, null, self::QUALITY);
            $extension = 'webp';
        }

        $content = (string) ob_get_clean();

        $path = ltrim("{$directory}/{$unique}.{$extension}", '/');
        Storage::disk($disk)->put($path, $content);

        return $path;
    }

    private function load(string $path): ?GdImage
    {
        $info = @getimagesize($path);

        if ($info === false) {
            return null;
        }

        // Animated GIFs: left untouched
        if ($info[2] === IMAGETYPE_GIF && $this->isAnimatedGif($path)) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if (! $image instanceof GdImage) {
            return null;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $image;
    }

    private function resize(GdImage $image, int $maxSize): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min(1, $maxSize / max($width, $height));

        if ($ratio >= 1) {
            return $image;
        }

        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($resized === false) {
            throw new RuntimeException((string) __('admin.messages.image_resize_failed'));
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, (int) imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    private function flatten(GdImage $image): GdImage
    {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));

        if ($flat === false) {
            return $image;
        }

        imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
        imagealphablending($flat, true);
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $flat;
    }

    private function isAnimatedGif(string $path): bool
    {
        $content = (string) file_get_contents($path);

        return preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $content) > 1;
    }
}
