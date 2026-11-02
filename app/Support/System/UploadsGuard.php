<?php

declare(strict_types=1);

namespace App\Support\System;

use RuntimeException;

/**
 * The uploads/ folder is in the web root: only the file types the admin accepts may be stored and served there.
 * Its .htaccess (stubs/uploads.htaccess) denies every other file and never lets the server run one as code; it is
 * written by the installer, by every update and after a content import. Update packages never touch uploads/.
 */
class UploadsGuard
{
    /** File types stored in uploads/ (images resized by the admin, PDFs of the certifications) */
    public const array EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'ico', 'pdf'];

    private ?string $directory = null;

    /**
     * Uses another folder (tests).
     */
    public function useDirectory(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function directory(): string
    {
        return $this->directory ?? public_path('uploads');
    }

    public function path(): string
    {
        return $this->directory().'/.htaccess';
    }

    public function template(): string
    {
        return base_path('stubs/uploads.htaccess');
    }

    /**
     * A file name that may be stored in uploads/: a known extension and no hidden files.
     */
    public static function allows(string $name): bool
    {
        $base = basename(str_replace('\\', '/', $name));

        return $base !== ''
            && ! str_starts_with($base, '.')
            && in_array(strtolower(pathinfo($base, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    public function isInstalled(): bool
    {
        return is_file($this->path()) && (string) file_get_contents($this->path()) === (string) file_get_contents($this->template());
    }

    /**
     * Writes (or rewrites) the .htaccess of uploads/.
     */
    public function write(): void
    {
        if (! is_dir($this->directory()) && ! @mkdir($this->directory(), 0755, true) && ! is_dir($this->directory())) {
            throw new RuntimeException((string) __('admin.messages.cannot_write', ['path' => $this->directory()]));
        }

        if ($this->isInstalled()) {
            return;
        }

        if (file_put_contents($this->path(), (string) file_get_contents($this->template()), LOCK_EX) === false) {
            throw new RuntimeException((string) __('admin.messages.cannot_write', ['path' => $this->path()]));
        }
    }
}
