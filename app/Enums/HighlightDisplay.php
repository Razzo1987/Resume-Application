<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum HighlightDisplay: string implements HasLabel
{
    use HasAdminLabel;

    case Exact = 'exact';
    case Plus = 'plus';

    protected static function labelGroup(): string
    {
        return 'highlight_display';
    }
}
