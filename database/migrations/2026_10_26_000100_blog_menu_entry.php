<?php

use App\Support\System\ReplaysOnImport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Since 2.0.2 the "Blog" menu item is controlled by the "Ultimi articoli" section ("Nel menu" yes/no, position,
 * title). Before, the item always appeared, at the end: "Nel menu" is turned on so that existing sites keep it.
 * This also runs again when importing content exported with an earlier version.
 */
return new class extends Migration implements ReplaysOnImport
{
    public function up(): void
    {
        $this->replay();
    }

    public function replay(): void
    {
        DB::table('page_sections')->where('key', 'latest_articles')->update(['in_menu' => true]);
    }
};
