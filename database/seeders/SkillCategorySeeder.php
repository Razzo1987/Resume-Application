<?php

namespace Database\Seeders;

use App\Models\SkillCategory;
use Illuminate\Database\Seeder;

class SkillCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'expertise' => ['it-IT' => 'Competenze', 'en-GB' => 'Expertise', 'en-US' => 'Expertise'],
            'soft-skills' => ['it-IT' => 'Soft skill', 'en-GB' => 'Soft skills', 'en-US' => 'Soft skills'],
            'tools' => ['it-IT' => 'Strumenti', 'en-GB' => 'Tools', 'en-US' => 'Tools'],
        ];

        $position = 0;

        foreach ($categories as $key => $name) {
            SkillCategory::query()->firstOrCreate(['key' => $key], [
                'name' => $name,
                'sort_order' => ++$position * 10,
            ]);
        }
    }
}
