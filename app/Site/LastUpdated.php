<?php

declare(strict_types=1);

namespace App\Site;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Date of the last change to the CV content ("Aggiornato a…" in the footer, dateModified in the JSON-LD).
 */
final class LastUpdated
{
    /** @var list<string> */
    public const array TABLES = [
        'profiles', 'social_links', 'organizations', 'experiences', 'projects', 'skills', 'skill_categories',
        'educations', 'certifications', 'language_skills', 'publications', 'interests', 'highlights',
        'page_sections', 'cv_documents',
    ];

    private ?Carbon $date = null;

    private bool $resolved = false;

    public function date(): ?Carbon
    {
        if (! $this->resolved) {
            $this->resolved = true;

            // A single query: the maximum of the maximums of each table
            $union = null;

            foreach (self::TABLES as $table) {
                $query = DB::table($table)->selectRaw('MAX(updated_at) AS latest');
                $union = $union === null ? $query : $union->unionAll($query);
            }

            $latest = DB::query()->fromSub($union, 'updates')->max('latest');

            $this->date = is_string($latest) && $latest !== '' ? Carbon::parse($latest) : null;
        }

        return $this->date;
    }
}
