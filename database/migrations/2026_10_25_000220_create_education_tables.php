<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->json('degree')->comment('(T) es. Laurea Magistrale');
            $table->json('field_of_study')->nullable()->comment('(T)');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable()->comment('Vuota = in corso');
            $table->string('grade', 20)->nullable();
            $table->string('grade_scale', 20)->nullable();
            $table->boolean('cum_laude')->default(false);
            $table->json('thesis_title')->nullable()->comment('(T)');
            $table->json('summary')->nullable()->comment('(T) HTML breve');
            $table->json('details')->nullable()->comment('(T) HTML, mostrato in un pannello espandibile');
            $table->string('link', 500)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->json('title')->comment('(T)');
            $table->string('type', 30)->default('certification');
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('credential_id')->nullable();
            $table->string('verification_url', 500)->nullable();
            $table->string('file')->nullable()->comment('PDF del certificato (disco uploads)');
            $table->string('badge')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('language_skills', function (Blueprint $table) {
            $table->id();
            $table->string('language_code', 10)->comment('ISO 639-1, es. en');
            $table->boolean('is_native')->default(false);
            $table->string('oral', 2)->nullable()->comment('QCER A1–C2');
            $table->string('written', 2)->nullable()->comment('QCER A1–C2');
            $table->string('comprehension', 2)->nullable()->comment('QCER A1–C2, facoltativo');
            $table->foreignId('certification_id')->nullable()->constrained()->nullOnDelete();
            $table->json('note')->nullable()->comment('(T)');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('language_skills');
        Schema::dropIfExists('certifications');
        Schema::dropIfExists('educations');
    }
};
