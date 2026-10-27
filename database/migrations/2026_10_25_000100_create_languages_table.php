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
            $table->string('code', 15)->unique()->comment('Full code, e.g. it-IT: it is also the URL prefix and the hreflang code');
            $table->string('name')->comment('Name in the language itself, e.g. Italiano');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('url_segments')->nullable()->comment('Overrides of the language pack URL segments');
            $table->json('string_overrides')->nullable()->comment('Overrides of the site strings (dotted key => text)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
