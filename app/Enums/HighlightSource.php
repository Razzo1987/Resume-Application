<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum HighlightSource: string implements HasLabel
{
    use HasAdminLabel;

    case Manual = 'manual';
    case YearsExperience = 'years_experience';
    case ProjectsCount = 'projects_count';
    case CertificationsCount = 'certifications_count';
    case PublicationsCount = 'publications_count';
    case ArticlesCount = 'articles_count';

    protected static function labelGroup(): string
    {
        return 'highlight_source';
    }

    public function isAutomatic(): bool
    {
        return $this !== self::Manual;
    }
}
