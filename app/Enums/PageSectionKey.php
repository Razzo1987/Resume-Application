<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Sezioni della pagina CV. L'ordine dei case è l'ordine di default.
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
     * Chiave della stringa del sito usata come titolo di default.
     */
    public function siteStringKey(): string
    {
        return match ($this) {
            self::LatestArticles => 'site.sections.blog',
            default => 'site.sections.'.$this->value,
        };
    }
}
