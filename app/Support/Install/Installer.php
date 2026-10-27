<?php

declare(strict_types=1);

namespace App\Support\Install;

use App\Models\Language;
use App\Models\Profile;
use App\Models\User;
use App\Support\Languages\LanguagePacks;
use App\Support\Languages\Locales;
use App\Support\Settings\Settings;
use App\Support\System\CoreProbe;
use App\Support\System\Htaccess;
use App\Support\System\Updater;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * First installation from the browser.
 *
 * Order: core/ protection → database (connection, tables, base data, administrator) → .htaccess → .env.
 * The .env is written last: as long as it is missing, the installer stays available and can be run again.
 */
class Installer
{
    public function __construct(
        private readonly InstallState $state,
        private readonly EnvWriter $env,
        private readonly Htaccess $htaccess,
        private readonly CoreProbe $probe,
        private readonly LanguagePacks $packs,
    ) {}

    /**
     * @param  array{url: string, owner: string, email: string, password: string, admin_path: string, admin_locale: string,
     *     default_language: string, languages: list<string>, db_host: string, db_port: int, db_database: string,
     *     db_username: string, db_password: string, force_https: bool, strip_www: bool, demo: bool}  $data
     * @return bool true if the database already contained an installation (content kept)
     */
    public function install(array $data): bool
    {
        $url = rtrim($data['url'], '/');

        // 1) The code must not be reachable from the browser (server without .htaccess support?)
        if ($this->probe->check($url) === CoreProbe::EXPOSED) {
            throw new InstallException((string) __('install.errors.core_exposed'));
        }

        // 2) Database
        $this->useDatabase($data);

        // Database already in use (previous installation or interrupted attempt): the content is kept
        $existing = Schema::hasTable('users') && DB::table('users')->exists();

        $languages = array_values(array_unique([$data['default_language'], ...$data['languages']]));
        $fallback = $data['default_language'] === 'en-GB' ? 'it-IT' : 'en-GB';

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.url' => $url,
            'app.locale' => $data['default_language'],
            'app.fallback_locale' => $fallback,
        ]);
        app()->forgetInstance('encrypter');

        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

            foreach ($languages as $position => $code) {
                Language::query()->firstOrCreate(['code' => $code], [
                    'name' => $this->packs->nativeName($code),
                    'is_active' => true,
                    'sort_order' => ($position + 1) * 10,
                ]);
            }

            app(Locales::class)->flush();

            if (! $existing && $data['demo']) {
                Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
            } elseif (! $existing) {
                Profile::current()->update(['full_name' => $data['owner']]);
            }

            // Administrator: a new one, or the one with the same email (new password; 2FA must be enabled again
            // because the secrets were encrypted with the previous key)
            $user = User::query()->firstOrNew(['email' => $data['email']]);
            $user->forceFill([
                'name' => $data['owner'],
                'password' => $data['password'],
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
            ])->save();

            app(Settings::class)->set(Updater::VERSION_KEY, app(Updater::class)->codeVersion());
        } catch (Throwable $exception) {
            report($exception);

            throw new InstallException(__('install.errors.database_setup', ['message' => Str::limit($exception->getMessage(), 300)]), previous: $exception);
        }

        // 3) Files and folders in the web root
        File::ensureDirectoryExists(public_path('uploads'));

        if ($this->probe->isReleaseLayout()) {
            $this->htaccess->write($data['force_https'], $data['strip_www']);
        }

        // 4) .env last: from here on the application is installed
        $this->env->write($this->state->envPath(), $this->envValues($data, $url, $fallback));

        return $existing;
    }

    /**
     * @param  array{db_host: string, db_port: int, db_database: string, db_username: string, db_password: string}  $data
     */
    public function useDatabase(array $data): void
    {
        $current = config('database.connections.mysql');

        // Same connection already configured (e.g. the installation is run again): reuse it
        if (config('database.default') === 'mysql' && is_array($current)
            && [$current['host'] ?? null, (int) ($current['port'] ?? 0), $current['database'] ?? null, $current['username'] ?? null, $current['password'] ?? null]
                === [$data['db_host'], $data['db_port'], $data['db_database'], $data['db_username'], $data['db_password']]) {
            DB::connection('mysql')->getPdo();

            return;
        }

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $data['db_host'],
            'database.connections.mysql.port' => $data['db_port'],
            'database.connections.mysql.database' => $data['db_database'],
            'database.connections.mysql.username' => $data['db_username'],
            'database.connections.mysql.password' => $data['db_password'],
        ]);

        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (Throwable $exception) {
            throw new InstallException(__('install.errors.database_connection', ['message' => Str::limit($exception->getMessage(), 200)]), previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|int|bool|null>
     */
    private function envValues(array $data, string $url, string $fallback): array
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: 'localhost');
        $secure = str_starts_with($url, 'https://');

        return [
            'APP_NAME' => (string) $data['owner'],
            'APP_ENV' => 'production',
            'APP_KEY' => (string) config('app.key'),
            'APP_DEBUG' => false,
            'APP_URL' => $url,
            'APP_LOCALE' => (string) $data['default_language'],
            'APP_FALLBACK_LOCALE' => $fallback,
            '' => null,
            'RESUME_ADMIN_PATH' => (string) $data['admin_path'],
            'RESUME_ADMIN_LOCALE' => (string) $data['admin_locale'],
            'RESUME_THEME' => 'base',
            ' ' => null,
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) $data['db_host'],
            'DB_PORT' => (int) $data['db_port'],
            'DB_DATABASE' => (string) $data['db_database'],
            'DB_USERNAME' => (string) $data['db_username'],
            'DB_PASSWORD' => (string) $data['db_password'],
            '  ' => null,
            'SESSION_DRIVER' => 'database',
            'SESSION_SECURE_COOKIE' => $secure,
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'FILESYSTEM_DISK' => 'local',
            'LOG_CHANNEL' => 'daily',
            'LOG_LEVEL' => 'warning',
            '   ' => null,
            'MAIL_MAILER' => 'sendmail',
            'MAIL_FROM_ADDRESS' => 'noreply@'.preg_replace('/^www\./', '', $host),
            'MAIL_FROM_NAME' => (string) $data['owner'],
        ];
    }
}
