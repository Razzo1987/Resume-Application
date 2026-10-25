<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Convenzione: le colonne json marcate (T) contengono testi per lingua {"it-IT": "...", "en-GB": "..."}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('photo')->nullable();
            $table->json('photo_alt')->nullable()->comment('(T)');
            $table->date('birth_date')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->json('location')->nullable()->comment('(T) città, es. Milano');
            $table->char('country_code', 2)->nullable()->comment('ISO 3166-1, per il JSON-LD');
            $table->json('headlines')->nullable()->comment('(T) qualifiche, una per riga');
            $table->json('bio')->nullable()->comment('(T) HTML');
            $table->date('career_start_date')->nullable()->comment('Se vuota si usa la prima esperienza di lavoro');
            $table->timestamps();
        });

        Schema::create('social_links', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30);
            $table->string('url', 500);
            $table->string('label')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('company')->index();
            $table->string('website', 500)->nullable();
            $table->string('logo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('social_links');
        Schema::dropIfExists('profiles');
    }
};
