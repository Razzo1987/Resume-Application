<?php

namespace Database\Seeders;

use App\Enums\HighlightDisplay;
use App\Enums\HighlightSource;
use App\Models\Highlight;
use Illuminate\Database\Seeder;

/**
 * Two automatic key figures to start with: years of experience and number of projects ("17+").
 */
class HighlightSeeder extends Seeder
{
    public function run(): void
    {
        if (Highlight::query()->exists()) {
            return;
        }

        Highlight::query()->create(['source' => HighlightSource::YearsExperience, 'display' => HighlightDisplay::Exact, 'icon' => 'briefcase', 'sort_order' => 10]);
        Highlight::query()->create(['source' => HighlightSource::ProjectsCount, 'display' => HighlightDisplay::Plus, 'icon' => 'project', 'sort_order' => 20]);
    }
}
