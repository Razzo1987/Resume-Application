<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Supported social platforms. The value is also the name of the theme's semantic icon.
 */
enum SocialPlatform: string implements HasLabel
{
    case LinkedIn = 'linkedin';
    case GitHub = 'github';
    case GitLab = 'gitlab';
    case X = 'x';
    case Bluesky = 'bluesky';
    case Mastodon = 'mastodon';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case YouTube = 'youtube';
    case Medium = 'medium';
    case SlideShare = 'slideshare';
    case StackOverflow = 'stackoverflow';
    case Website = 'website';

    public function getLabel(): string
    {
        return match ($this) {
            self::LinkedIn => 'LinkedIn',
            self::GitHub => 'GitHub',
            self::GitLab => 'GitLab',
            self::X => 'X (Twitter)',
            self::Bluesky => 'Bluesky',
            self::Mastodon => 'Mastodon',
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::YouTube => 'YouTube',
            self::Medium => 'Medium',
            self::SlideShare => 'SlideShare',
            self::StackOverflow => 'Stack Overflow',
            self::Website => __('admin.enums.social.website'),
        };
    }

    /**
     * Name shown on the public site, in the language of the page: the admin label of "website" comes from the admin
     * language files, which the site does not load.
     */
    public function siteLabel(): string
    {
        return $this === self::Website ? (string) __('site.contact.website') : $this->getLabel();
    }

    public function icon(): string
    {
        return $this->value;
    }
}
