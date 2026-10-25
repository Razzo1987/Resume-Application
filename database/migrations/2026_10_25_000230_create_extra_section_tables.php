<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interests', function (Blueprint $table) {
            $table->id();
            $table->json('name')->comment('(T)');
            $table->json('description')->nullable()->comment('(T) una riga');
            $table->string('icon', 40)->nullable()->comment('Icona semantica');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('highlights', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30)->default('manual')->comment('manual | years_experience | projects_count | ...');
            $table->decimal('value', 12, 2)->nullable()->comment('Solo per source = manual');
            $table->string('display', 10)->default('exact')->comment('exact = 460 · plus = 460+');
            $table->string('unit', 10)->nullable()->comment('es. %, k');
            $table->json('label')->nullable()->comment('(T) per le fonti automatiche c\'è un testo di default');
            $table->string('icon', 40)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('article');
            $table->json('title')->comment('(T)');
            $table->json('venue')->nullable()->comment('(T) editore, rivista, evento');
            $table->date('published_on')->nullable();
            $table->string('coauthors')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('file')->nullable()->comment('PDF (disco uploads)');
            $table->json('description')->nullable()->comment('(T)');
            $table->string('language_code', 10)->nullable()->comment('Lingua della pubblicazione');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('in_menu')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('title')->nullable()->comment('(T) titolo personalizzato; vuoto = testo del language pack');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('publications');
        Schema::dropIfExists('highlights');
        Schema::dropIfExists('interests');
    }
};
