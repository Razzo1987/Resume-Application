<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 15)->unique()->comment('Codice completo, es. it-IT: è anche il prefisso URL e il codice hreflang');
            $table->string('name')->comment('Nome nella lingua stessa, es. Italiano');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('url_segments')->nullable()->comment('Override dei segmenti URL del language pack');
            $table->json('string_overrides')->nullable()->comment('Override delle stringhe del sito (chiave puntata => testo)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
