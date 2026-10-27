<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatar of the admin user generated locally (SVG with the initials).
 * Replaces Filament's default provider, which downloads the image from ui-avatars.com.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $initials = collect(preg_split('/\s+/u', trim(Filament::getNameForDefaultAvatar($record))) ?: [])
            ->map(fn (string $word): string => mb_strtoupper(mb_substr((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $word), 0, 1)))
            ->filter()
            ->take(2)
            ->implode('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#1e293b"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#fff" '
            .'font-family="system-ui,-apple-system,Segoe UI,Roboto,sans-serif" font-size="26" font-weight="600">'
            .htmlspecialchars($initials !== '' ? $initials : '?', ENT_XML1)
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
