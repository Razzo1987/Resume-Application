<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Themes\ThemeManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Preview image of an installed theme (or of one of its variants; ?device=mobile for the phone one) for the Themes page. The images stay in the theme
 * folder (core/themes, not reachable from the browser) and are served only to administrators, only if theme.json
 * declares them with an image name (Theme::PREVIEW_PATTERN).
 */
class ThemePreviewController extends Controller
{
    private const array TYPES = ['webp' => 'image/webp', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];

    public function __invoke(Request $request, ThemeManager $themes, string $theme, ?string $variant = null): BinaryFileResponse
    {
        abort_unless(preg_match('/^[a-z0-9][a-z0-9-]*$/', $theme) === 1 && $themes->isInstalled($theme), 404);

        $file = $themes->get($theme)->previewFile($variant, $request->query('device') === 'mobile');
        $root = realpath($themes->path($theme));
        $real = $file !== null ? realpath($file) : false;
        $type = self::TYPES[strtolower(pathinfo((string) $file, PATHINFO_EXTENSION))] ?? null;

        abort_if($real === false || $root === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR) || $type === null, 404);

        return response()->file($real, [
            'Content-Type' => $type,
            'Cache-Control' => 'private, max-age=86400',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
