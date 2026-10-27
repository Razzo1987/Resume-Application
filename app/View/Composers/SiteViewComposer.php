<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Site\Content\RichText;
use App\Site\Formatting\Dates;
use App\Site\LayoutFactory;
use App\Site\Media\Images;
use App\Site\Page;
use App\Site\SiteUrls;
use Illuminate\View\View;

/**
 * Variables available in all theme views that receive a $page:
 * $layout (menu, languages, contacts, footer) and the content tools
 * ($richText, $dates, $images, $urls).
 */
class SiteViewComposer
{
    public function __construct(
        private readonly LayoutFactory $layouts,
        private readonly RichText $richText,
        private readonly Dates $dates,
        private readonly Images $images,
        private readonly SiteUrls $urls,
    ) {}

    public function compose(View $view): void
    {
        $page = $view->getData()['page'] ?? null;

        if (! $page instanceof Page) {
            return;
        }

        $view->with([
            'layout' => $this->layouts->make($page),
            'richText' => $this->richText,
            'dates' => $this->dates,
            'images' => $this->images,
            'urls' => $this->urls,
        ]);
    }
}
