<?php

use App\Support\Settings\Settings;
use App\Support\Stats\Aggregator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Statistics: the totals of the days before 2.3 have no hours, channels or system versions, so without filters the
 * "Days and hours" grid and some rankings showed only the days after the update. Their totals are recalculated from
 * the single visits still kept, a few days at a time (Aggregator::rebuild): not here, so that the update stays quick.
 */
return new class extends Migration
{
    public function up(): void
    {
        $first = DB::table('stat_visits')->min('day');

        if ($first !== null) {
            app(Settings::class)->set(Aggregator::REBUILD, substr((string) $first, 0, 10));
        }
    }

    public function down(): void
    {
        app(Settings::class)->set(Aggregator::REBUILD, null);
    }
};
