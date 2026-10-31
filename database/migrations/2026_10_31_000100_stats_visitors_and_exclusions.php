<?php

use App\Support\Settings\Settings;
use App\Support\Stats\Channels;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Statistics 2.3: visitors and sessions recognized for 24 hours without cookies (see App\Support\Stats\Visitors),
 * channel and local hour of each visit, operating system version, exclusions with three statuses (they replace the
 * admin IPs), bots and crawlers, pages not found.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stat_visits', function (Blueprint $table) {
            $table->char('visitor_id', 16)->nullable()->after('ip')->comment('Random code, recognized for 24 hours at most');
            $table->char('session_id', 16)->nullable()->after('visitor_id')->comment('Random code, new after 30 minutes without pages');
            $table->char('ip_hash', 32)->nullable()->after('session_id')->comment('HMAC of the IP with the daily salt, removed after 24 hours');
            $table->char('visitor_hash', 32)->nullable()->after('ip_hash')->comment('HMAC of IP + user agent with the daily salt, removed after 24 hours');
            $table->string('channel', 20)->nullable()->after('click_source')->comment('direct, search, social, ai, email, sites, campaign');
            $table->unsignedTinyInteger('hour')->nullable()->after('day')->comment('Hour of the visit in the time zone of the site');
            $table->string('os_version', 20)->nullable()->after('os');
            $table->index(['visitor_hash', 'visited_at']);
            $table->index('ip_hash');
            $table->index('session_id');
            $table->index('visitor_id');
            $table->index('asn');
            $table->index('ip');
        });

        // Random salts of the hashes, one per day of the site; only today's and yesterday's are kept
        Schema::create('stat_salts', function (Blueprint $table) {
            $table->id();
            $table->date('day')->unique();
            $table->char('salt', 64);
            $table->dateTime('created_at');
        });

        // IP addresses, networks and ASNs not counted (or always counted)
        Schema::create('stat_exclusions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10)->comment('ip, network, asn');
            $table->string('value', 45);
            $table->string('status', 10)->comment('auto (address used for the admin), excluded, always');
            $table->string('note', 150)->nullable();
            $table->dateTime('last_seen_at')->nullable()->comment('Last admin access (auto entries)');
            $table->dateTime('created_at');
            $table->unique(['type', 'value']);
        });

        // Requests from bots and crawlers per day (no address, only the name)
        Schema::create('stat_bots', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('name', 100);
            $table->string('category', 20)->comment('search, ai, crawler, monitor, social, feed, tool, other');
            $table->unsignedInteger('hits');
            $table->unique(['day', 'name']);
        });

        // Addresses of the site that do not exist (path without parameters)
        Schema::create('stat_not_found', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique();
            $table->unsignedInteger('hits');
            $table->unsignedInteger('human_hits')->comment('Requests that do not come from bots');
            $table->boolean('probe')->comment('Typical of attack attempts (wp-login.php, .env...)');
            $table->string('last_referrer_host', 150)->nullable();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->index('last_seen_at');
        });

        // The admin IPs become automatic exclusions
        if (Schema::hasTable('stat_admin_ips')) {
            foreach (DB::table('stat_admin_ips')->get() as $row) {
                DB::table('stat_exclusions')->insertOrIgnore([
                    'type' => 'ip', 'value' => $row->ip, 'status' => 'auto', 'last_seen_at' => $row->last_seen_at, 'created_at' => $row->last_seen_at,
                ]);
            }

            Schema::drop('stat_admin_ips');
        }

        // Channel and local hour of the visits already recorded
        $timezone = app(Settings::class)->timezone();

        DB::table('stat_visits')->select(['id', 'visited_at', 'referrer_host', 'utm_source', 'utm_medium', 'utm_campaign', 'click_source'])
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use ($timezone): void {
                foreach ($rows as $row) {
                    DB::table('stat_visits')->where('id', $row->id)->update([
                        'channel' => Channels::of($row->referrer_host, $row->utm_source, $row->utm_medium, $row->utm_campaign, $row->click_source),
                        'hour' => Carbon::parse($row->visited_at, 'UTC')->setTimezone($timezone)->hour,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::create('stat_admin_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->dateTime('last_seen_at');
        });

        Schema::dropIfExists('stat_not_found');
        Schema::dropIfExists('stat_bots');
        Schema::dropIfExists('stat_exclusions');
        Schema::dropIfExists('stat_salts');

        Schema::table('stat_visits', function (Blueprint $table) {
            $table->dropIndex(['visitor_hash', 'visited_at']);
            $table->dropIndex(['ip_hash']);
            $table->dropIndex(['session_id']);
            $table->dropIndex(['visitor_id']);
            $table->dropIndex(['asn']);
            $table->dropIndex(['ip']);
            $table->dropColumn(['visitor_id', 'session_id', 'ip_hash', 'visitor_hash', 'channel', 'hour', 'os_version']);
        });
    }
};
