<?php

declare(strict_types=1);

namespace App\Support\System;

use App\Site\Cache\PageCache;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Completamento di un aggiornamento dopo aver estratto il nuovo pacchetto:
 * migrazioni, cache, blocco .htaccess, versione installata.
 */
class Updater
{
    public const string VERSION_KEY = 'system.installed_version';

    public function __construct(
        private readonly Settings $settings,
        private readonly Htaccess $htaccess,
        private readonly CoreProbe $probe,
        private readonly PageCache $pages,
    ) {}

    /**
     * Versione dei file (VERSION del pacchetto).
     */
    public function codeVersion(): string
    {
        $file = base_path('VERSION');

        return is_file($file) ? trim((string) file_get_contents($file)) : 'dev';
    }

    /**
     * Versione per cui è stato completato l'ultimo aggiornamento (o l'installazione).
     */
    public function installedVersion(): ?string
    {
        $version = $this->settings->get(self::VERSION_KEY);

        return is_string($version) ? $version : null;
    }

    public function isPending(): bool
    {
        return $this->installedVersion() !== $this->codeVersion();
    }

    /**
     * @return list<string> esito dei passaggi
     */
    public function complete(): array
    {
        $log = [];

        Artisan::call('migrate', ['--force' => true]);
        $log[] = trim(Artisan::output()) ?: 'migrate: ok';

        $log = [...$log, ...$this->clearCaches()];

        if ($this->probe->isReleaseLayout() && is_file($this->htaccess->template())) {
            $options = $this->htaccess->options();
            $this->htaccess->write($options['force_https'], $options['strip_www']);
            $log[] = '.htaccess: ok';
        }

        $this->settings->set(self::VERSION_KEY, $this->codeVersion());
        $log[] = 'version: '.$this->codeVersion();

        return $log;
    }

    /**
     * @return list<string>
     */
    public function clearCaches(): array
    {
        $log = [];

        foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear'] as $command) {
            try {
                Artisan::call($command);
                $log[] = $command.': ok';
            } catch (Throwable $exception) {
                $log[] = $command.': '.$exception->getMessage();
            }
        }

        $this->pages->flush();
        $this->settings->flush();
        $log[] = 'page cache: ok';

        return $log;
    }
}
