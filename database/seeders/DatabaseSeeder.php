<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Base data of every installation (languages, skill categories, CV sections, key figures).
 * The demo data is separate: php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LanguageSeeder::class,
            SkillCategorySeeder::class,
            PageSectionSeeder::class,
            HighlightSeeder::class,
        ]);
    }
}
