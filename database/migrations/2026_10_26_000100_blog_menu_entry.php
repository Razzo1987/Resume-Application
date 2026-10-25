<?php

use App\Support\System\ReplaysOnImport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dalla 2.0.2 la voce "Blog" del menu è decisa dalla sezione "Ultimi articoli" (Nel menu sì/no, posizione, titolo).
 * Prima la voce compariva sempre, in fondo: si accende "Nel menu" perché i siti esistenti la mantengano.
 * Si ripete anche importando contenuti esportati con una versione precedente.
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
