<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Dati di base di ogni installazione (lingue, categorie di competenze, sezioni del CV, numeri chiave).
 * I dati dimostrativi sono a parte: php artisan db:seed --class=DemoSeeder
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
