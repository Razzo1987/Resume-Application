<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PublicationType: string implements HasLabel
{
    use HasAdminLabel;

    case Article = 'article';
    case Paper = 'paper';
    case Thesis = 'thesis';
    case Book = 'book';
    case Talk = 'talk';
    case Webinar = 'webinar';
    case Presentation = 'presentation';
    case Podcast = 'podcast';

    protected static function labelGroup(): string
    {
        return 'publication_type';
    }

    /**
     * Tipo Schema.org per il JSON-LD.
     */
    public function schemaType(): string
    {
        return match ($this) {
            self::Article => 'Article',
            self::Paper => 'ScholarlyArticle',
            self::Thesis => 'Thesis',
            self::Book => 'Book',
            self::Talk, self::Webinar, self::Presentation => 'PresentationDigitalDocument',
            self::Podcast => 'PodcastEpisode',
        };
    }
}
