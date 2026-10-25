<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExperienceKind: string implements HasLabel
{
    use HasAdminLabel;

    case Work = 'work';
    case Volunteering = 'volunteering';

    protected static function labelGroup(): string
    {
        return 'experience_kind';
    }
}
