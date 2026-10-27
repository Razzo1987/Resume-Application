<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Sections of the CV page. The order of the cases is the default order.
 */
enum PageSectionKey: string implements HasLabel
{
    case About = 'about';
    case Highlights = 'highlights';
    case Experience = 'experience';
    case Volunteering = 'volunteering';
    case Projects = 'projects';
    case Education = 'education';
    case Certifications = 'certifications';
    case Languages = 'languages';
    case Publications = 'publications';
    case Interests = 'interests';
    case LatestArticles = 'latest_articles';

    public function getLabel(): string
    {
        return __('admin.enums.section.'.$this->value);
    }

    /**
     * Key of the site string used as the default title.
     */
    public function siteStringKey(): string
    {
        return match ($this) {
            self::LatestArticles => 'site.sections.blog',
            default => 'site.sections.'.$this->value,
        };
    }
}
