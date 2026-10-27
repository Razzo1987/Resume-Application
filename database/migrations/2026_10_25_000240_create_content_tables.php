<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('path')->comment('"local" disk (private): cv/cv_it-IT_20261025-143012.pdf');
            $table->string('original_name');
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->boolean('is_current')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['language_id', 'is_current']);
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->json('title')->comment('(T)');
            $table->json('slug')->nullable()->comment('(T)');
            $table->json('excerpt')->nullable()->comment('(T)');
            $table->json('body')->nullable()->comment('(T) HTML');
            $table->string('cover_image')->nullable();
            $table->json('cover_alt')->nullable()->comment('(T)');
            $table->string('status', 10)->default('draft')->comment('draft | published');
            $table->dateTime('published_at')->nullable()->comment('Future date = scheduled publication');
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 500)->unique();
            $table->string('to_path', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('cv_documents');
    }
};
