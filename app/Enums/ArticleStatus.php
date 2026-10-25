<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ArticleStatus: string implements HasLabel
{
    use HasAdminLabel;

    case Draft = 'draft';
    case Published = 'published';

    protected static function labelGroup(): string
    {
        return 'article_status';
    }
}
