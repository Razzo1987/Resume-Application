<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->json('name')->comment('(T)');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_category_id')->constrained()->cascadeOnDelete();
            $table->json('name')->comment('(T)');
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->default('work')->comment('work | volunteering');
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->json('role')->comment('(T)');
            $table->json('location')->nullable()->comment('(T)');
            $table->date('start_date');
            $table->date('end_date')->nullable()->comment('Empty = ongoing');
            $table->json('summary')->nullable()->comment('(T) short HTML, shown in the CV');
            $table->json('details')->nullable()->comment('(T) HTML, detail page');
            $table->json('slug')->nullable()->comment('(T)');
            $table->json('seo_title')->nullable()->comment('(T)');
            $table->json('seo_description')->nullable()->comment('(T)');
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->index(['kind', 'end_date', 'start_date']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experience_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->json('title')->comment('(T)');
            $table->json('role')->nullable()->comment('(T)');
            $table->json('sector')->nullable()->comment('(T)');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->json('summary')->nullable()->comment('(T) short HTML');
            $table->json('details')->nullable()->comment('(T) HTML, detail page');
            $table->string('image')->nullable();
            $table->json('image_alt')->nullable()->comment('(T)');
            $table->json('slug')->nullable()->comment('(T)');
            $table->json('seo_title')->nullable()->comment('(T)');
            $table->json('seo_description')->nullable()->comment('(T)');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->index(['end_date', 'start_date']);
        });

        Schema::create('experience_skill', function (Blueprint $table) {
            $table->foreignId('experience_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['experience_id', 'skill_id']);
        });

        Schema::create('project_skill', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_skill');
        Schema::dropIfExists('experience_skill');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('experiences');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('skill_categories');
    }
};
