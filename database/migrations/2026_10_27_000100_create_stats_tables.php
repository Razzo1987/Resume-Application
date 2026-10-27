<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Statistiche delle visite, senza cookie (vedi App\Support\Stats\Tracker).
 * Non fanno parte dei contenuti: le esportazioni dei contenuti non le includono.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Una riga per pagina vista o CV scaricato; cancellate dopo stats.retention_months
        Schema::create('stat_visits', function (Blueprint $table) {
            $table->id();
            $table->dateTime('visited_at');
            $table->date('day');
            $table->string('page', 60)->comment('cv, blog, privacy, cv-download, project:12, experience:3, article:5');
            $table->string('path', 255);
            $table->string('locale', 12);
            $table->string('referrer_host', 150)->nullable()->comment('Solo siti esterni');
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('click_source', 30)->nullable()->comment('Da fbclid, gclid, msclkid... (il codice non si salva)');
            $table->string('device', 20)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('browser_version', 20)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 7, 4)->nullable();
            $table->decimal('longitude', 7, 4)->nullable();
            $table->unsignedInteger('asn')->nullable();
            $table->string('as_org', 150)->nullable();
            $table->string('ip', 45)->comment('Accorciato: 93.44.12.x o i primi 48 bit IPv6');
            $table->index(['day', 'page']);
            $table->index('visited_at');
        });

        // Totali per giorno, pagina e dimensione (restano dopo la cancellazione delle singole visite)
        Schema::create('stat_daily', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('page', 60);
            $table->string('dimension', 20)->comment('total, locale, device, os, browser, country, city, referrer, utm_source, utm_medium, utm_campaign, click_source, asn');
            $table->string('value', 200);
            $table->unsignedInteger('views');
            $table->decimal('latitude', 7, 4)->nullable()->comment('Solo per le città');
            $table->decimal('longitude', 7, 4)->nullable();
            $table->unique(['day', 'page', 'dimension', 'value']);
            $table->index(['dimension', 'day']);
        });

        // IP usati nell'admin: le visite da questi indirizzi non contano (per stats.admin_ip_days dall'ultimo accesso)
        Schema::create('stat_admin_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->dateTime('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stat_admin_ips');
        Schema::dropIfExists('stat_daily');
        Schema::dropIfExists('stat_visits');
    }
};
