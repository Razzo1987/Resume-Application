<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrganizationType: string implements HasLabel
{
    use HasAdminLabel;

    case Company = 'company';
    case Client = 'client';
    case University = 'university';
    case School = 'school';
    case Certifier = 'certifier';
    case Association = 'association';
    case Other = 'other';

    protected static function labelGroup(): string
    {
        return 'organization_type';
    }
}
