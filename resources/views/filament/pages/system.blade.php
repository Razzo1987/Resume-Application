<x-filament-panels::page>
    @if ($updatePending)
        <x-filament::section icon="heroicon-o-arrow-path" icon-color="warning">
            <x-slot name="heading">{{ __('admin.system.update_pending', ['version' => $codeVersion]) }}</x-slot>
            <p>{{ __('admin.system.complete_update_help') }}</p>
        </x-filament::section>
    @endif

    @if ($protection === \App\Support\System\CoreProbe::EXPOSED)
        <x-filament::section icon="heroicon-o-shield-exclamation" icon-color="danger">
            <x-slot name="heading">{{ __('admin.system.protection.exposed') }}</x-slot>
            <p>{{ __('admin.system.exposed_help') }}</p>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">{{ __('admin.system.status') }}</x-slot>
        <dl class="resume-system-list">
            <dt>{{ __('admin.system.version') }}</dt>
            <dd>{{ $codeVersion }} @if ($installedVersion && $installedVersion !== $codeVersion) ({{ __('admin.system.installed', ['version' => $installedVersion]) }}) @endif</dd>
            <dt>{{ __('admin.system.maintenance') }}</dt>
            <dd>{{ $maintenance ? __('admin.system.on') : __('admin.system.off') }}</dd>
            <dt>{{ __('admin.system.core') }}</dt>
            <dd>{{ __('admin.system.protection.'.$protection) }}</dd>
            @if ($releaseLayout)
                <dt>.htaccess</dt>
                <dd>
                    {{ $htaccess['installed'] ? __('admin.system.htaccess_ok') : __('admin.system.htaccess_missing') }}
                    · HTTPS: {{ $htaccess['force_https'] ? __('admin.system.on') : __('admin.system.off') }}
                    · {{ __('admin.system.no_www') }}: {{ $htaccess['strip_www'] ? __('admin.system.on') : __('admin.system.off') }}
                </dd>
            @endif
            @if ($orphansAvailable)
                <dt>{{ __('admin.system.orphans') }}</dt>
                <dd>{{ $orphanCount }}</dd>
            @endif
            <dt>{{ __('admin.system.theme') }}</dt>
            <dd>{{ $theme }}</dd>
            <dt>PHP · Laravel · {{ __('admin.system.database') }}</dt>
            <dd>{{ $php }} · {{ $laravel }} · {{ $database }}</dd>
            <dt>{{ __('admin.system.paths') }}</dt>
            <dd><code>{{ $paths['core'] }}</code><br><code>{{ $paths['webroot'] }}</code></dd>
        </dl>
    </x-filament::section>

    <x-filament::section icon="heroicon-o-arrow-down-circle">
        <x-slot name="heading">{{ __('admin.updates.title') }}</x-slot>
        <x-slot name="afterHeader">
            <div class="resume-update-actions">{{ $this->checkUpdatesAction }} {{ $this->githubTokenAction }}</div>
        </x-slot>

        <dl class="resume-system-list">
            <dt>{{ __('admin.updates.installed') }}</dt>
            <dd>{{ $codeVersion }}</dd>
            <dt>{{ __('admin.updates.latest') }}</dt>
            <dd>
                @if ($updateCheck['release'])
                    <a href="{{ $updateCheck['release']->url }}" target="_blank" rel="noopener" class="resume-link">{{ $updateCheck['release']->name }}</a>
                    @if ($updateCheck['release']->publishedAt) · {{ \Illuminate\Support\Carbon::parse($updateCheck['release']->publishedAt)->translatedFormat('j F Y') }} @endif
                    @unless ($updateAvailable) · {{ __('admin.updates.up_to_date') }} @endunless
                @else
                    {{ __('admin.updates.unknown') }}
                @endif
            </dd>
            <dt>{{ __('admin.updates.last_check') }}</dt>
            <dd>
                {{ $updateCheck['checked_at'] ? $updateCheck['checked_at']->diffForHumans() : __('admin.updates.never') }}
                @if ($updateCheck['error']) <br><span class="resume-update-problem">{{ $updateCheck['error'] }}</span> @endif
            </dd>
            <dt>{{ __('admin.updates.repository') }}</dt>
            <dd><code>{{ $updateRepository }}</code> · {{ __('admin.updates.token_status.'.($updateTokenSource ?? 'none')) }}</dd>
            <dt>{{ __('admin.updates.auto.title') }}</dt>
            <dd>
                {{ __('admin.updates.auto.levels.'.$autoLevel) }} {{ $this->autoUpdatesAction }}
                @if ($autoLevel !== 'off' && ! $cronActive)
                    <br><span class="resume-update-problem">{{ __('admin.updates.auto.needs_cron') }}</span>
                @endif
                @if ($autoEvent)
                    <br>{{ __('admin.updates.auto.last_event.'.$autoEvent['type'], ['version' => $autoEvent['version'], 'when' => $autoEvent['at']->diffForHumans()]) }}
                @endif
            </dd>
        </dl>

        @if (! $updateSupported)
            <p class="resume-update-note">{{ __('admin.updates.problems.not_packaged') }}</p>
        @else
            @if ($updateAvailable)
                <div class="resume-update-box" x-data="resumeUpdateDownload()">
                    <h3 class="resume-update-title">{{ __('admin.updates.available', ['version' => $updateAvailable->version]) }}</h3>
                    @if ($updateNotes)
                        <details class="resume-update-notes">
                            <summary>{{ __('admin.updates.notes_title') }}</summary>
                            <div class="resume-update-markdown">{{ $updateNotes }}</div>
                        </details>
                    @endif
                    @if ($updateAvailable->package)
                        <x-filament::button icon="heroicon-o-cloud-arrow-down" x-on:click="download()" x-bind:disabled="busy">
                            {{ __('admin.updates.download_install', ['size' => \Illuminate\Support\Number::fileSize($updateAvailable->package['size'], 1)]) }}
                        </x-filament::button>
                        <div class="resume-update-progress" x-show="total > 0" x-cloak>
                            <progress x-bind:max="total" x-bind:value="received"></progress>
                            <span x-text="Math.round(received / total * 100) + '%'"></span>
                        </div>
                        <p class="resume-update-problem" x-show="error" x-text="error" x-cloak></p>
                    @else
                        <p class="resume-update-problem">{{ __('admin.updates.errors.no_package', ['version' => $updateAvailable->version]) }}</p>
                    @endif
                </div>
            @endif

            <div class="resume-update-box" x-data="resumeUpdateUpload(@js($updateUploadUrl), @js(csrf_token()))">
                <h3 class="resume-update-title">{{ __('admin.updates.upload_title') }}</h3>
                <p>{{ __('admin.updates.upload_help') }}</p>
                <div class="resume-update-upload">
                    <input type="file" accept=".zip,application/zip" x-ref="file" x-bind:disabled="busy">
                    <x-filament::button color="gray" icon="heroicon-o-arrow-up-tray" x-on:click="upload()" x-bind:disabled="busy">
                        {{ __('admin.updates.upload') }}
                    </x-filament::button>
                </div>
                <div class="resume-update-progress" x-show="size > 0" x-cloak>
                    <progress x-bind:max="size" x-bind:value="sent"></progress>
                    <span x-text="Math.round(sent / size * 100) + '%'"></span>
                </div>
                <p class="resume-update-problem" x-show="error" x-text="error" x-cloak></p>
            </div>

            @if ($updatePackages)
                <div class="resume-update-box">
                    <h3 class="resume-update-title">{{ __('admin.updates.packages') }}</h3>
                    <ul class="resume-update-packages">
                        @foreach ($updatePackages as $file => $label)
                            <li><span>{{ $label }}</span> {{ ($this->installAction)(['package' => $file]) }} {{ ($this->deletePackageAction)(['package' => $file]) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif

        <details class="resume-update-manual">
            <summary>{{ __('admin.system.how_to_update') }}</summary>
            <ol class="resume-system-steps">
                <li>{{ __('admin.system.update_step_1') }}</li>
                <li>{{ __('admin.system.update_step_2') }}</li>
                <li>{{ __('admin.system.update_step_3') }}</li>
                <li>{{ __('admin.system.update_step_4') }}</li>
            </ol>
        </details>
    </x-filament::section>

    <x-filament::section icon="heroicon-o-clock" id="cron">
        <x-slot name="heading">{{ __('admin.cron.title') }}</x-slot>
        <dl class="resume-system-list">
            <dt>{{ __('admin.cron.status') }}</dt>
            <dd>
                @if ($cronActive)
                    <span class="resume-update-ok">{{ __('admin.cron.active') }}</span>
                    · {{ __('admin.cron.last', ['when' => $cronLast->diffForHumans()]) }}
                    @if ($cronPhp) · PHP {{ $cronPhp }} @endif
                @elseif ($cronLast)
                    <span class="resume-update-problem">{{ __('admin.cron.stopped', ['when' => $cronLast->diffForHumans()]) }}</span>
                @else
                    {{ __('admin.cron.inactive') }}
                @endif
                @if ($cronPhp && version_compare($cronPhp, $phpRequired, '<'))
                    <br><span class="resume-update-problem">{{ __('admin.cron.php_old', ['version' => $cronPhp, 'required' => $phpRequired]) }}</span>
                @endif
            </dd>
        </dl>

        @unless ($cronActive)
            <div class="resume-update-box">
                <h3 class="resume-update-title">{{ __('admin.cron.setup_title') }}</h3>
                <ol class="resume-system-steps">
                    <li>{{ __('admin.cron.setup_1') }}</li>
                    <li>{{ __('admin.cron.setup_2') }}</li>
                    <li>{{ __('admin.cron.setup_3') }} <pre class="resume-system-log resume-cron-command">{{ $cronCommand }}</pre></li>
                    <li>{{ __('admin.cron.setup_4') }}</li>
                </ol>
            </div>
        @endunless

        <div class="resume-update-box">
            <h3 class="resume-update-title">{{ __('admin.cron.jobs') }}</h3>
            <table class="resume-cron-jobs">
                <thead>
                    <tr><th>{{ __('admin.cron.job') }}</th><th>{{ __('admin.cron.every') }}</th><th>{{ __('admin.cron.last_run') }}</th><th>{{ __('admin.cron.result') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($cronJobs as $name => $job)
                        <tr>
                            <td>{{ __('admin.cron.names.'.$name) }}@unless ($job['light']) <span class="resume-cron-tag">{{ __('admin.cron.cron_only') }}</span>@endunless</td>
                            <td>{{ $job['every'] >= 60 ? trans_choice('admin.cron.hours', intdiv($job['every'], 60), ['count' => intdiv($job['every'], 60)]) : __('admin.cron.minutes', ['count' => $job['every']]) }}</td>
                            <td>{{ $job['at'] ? $job['at']->diffForHumans() : __('admin.updates.never') }}</td>
                            <td @class(['resume-update-problem' => ! $job['ok']])>{{ \App\Support\Cron\Cron::describe($job['message']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <script>
        // Download da GitHub a blocchi (ogni blocco è una richiesta breve), poi controlli e installazione
        window.resumeUpdateDownload = () => ({
            busy: false, received: 0, total: 0, error: null,
            async download() {
                this.busy = true;
                this.error = null;
                try {
                    for (;;) {
                        const result = await this.$wire.downloadChunk();
                        if (result.error) { this.error = result.error; return; }
                        this.received = result.received;
                        this.total = result.total;
                        if (result.done) { await this.$wire.mountAction('install', { package: result.file }); return; }
                    }
                } finally {
                    this.busy = false;
                }
            },
        });

        // Caricamento a blocchi da 1 MB: il limite di caricamento dell'hosting non conta
        window.resumeUpdateUpload = (url, token) => ({
            busy: false, sent: 0, size: 0, error: null,
            async upload() {
                const file = this.$refs.file.files[0];
                if (!file) { return; }
                const chunk = 1024 * 1024;
                const count = Math.ceil(file.size / chunk);
                const id = Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join('');
                const name = file.name.replace(/[^A-Za-z0-9._-]/g, '-');
                this.busy = true;
                this.error = null;
                this.size = file.size;
                this.sent = 0;
                try {
                    let index = 0;
                    while (index < count) {
                        const query = new URLSearchParams({ id, index, count, name });
                        const response = await fetch(url + '?' + query, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/octet-stream', 'Accept': 'application/json' },
                            body: file.slice(index * chunk, (index + 1) * chunk),
                        });
                        const data = await response.json().catch(() => ({ error: response.status + ' ' + response.statusText }));
                        if (response.status === 409) { index = data.next; continue; }
                        if (!response.ok || data.error) { throw new Error(data.error || response.statusText); }
                        index++;
                        this.sent = Math.min(index * chunk, file.size);
                        if (data.done) { await this.$wire.mountAction('install', { package: data.file }); }
                    }
                } catch (exception) {
                    this.error = exception.message;
                } finally {
                    this.busy = false;
                }
            },
        });
    </script>

    @if ($log)
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.system.log') }}</x-slot>
            <pre class="resume-system-log">{{ implode("\n", $log) }}</pre>
        </x-filament::section>
    @endif

    <style>
        .resume-system-list { display: grid; grid-template-columns: minmax(9rem, max-content) 1fr; gap: .5rem 1.5rem; margin: 0; }
        .resume-system-list dt { font-weight: 600; }
        .resume-system-list dd { margin: 0; word-break: break-word; }
        .resume-system-steps { margin: 0; padding-left: 1.25rem; list-style: decimal; }
        .resume-system-steps li { margin: .35rem 0; }
        .resume-system-log { margin: 0; white-space: pre-wrap; font-size: .85rem; }
        .resume-update-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
        .resume-update-box { margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid rgb(0 0 0 / .08); }
        .dark .resume-update-box { border-top-color: rgb(255 255 255 / .1); }
        .resume-update-title { margin: 0 0 .5rem; font-weight: 600; }
        .resume-update-box p { margin: 0 0 .75rem; }
        .resume-update-upload { display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; }
        .resume-update-progress { display: flex; gap: .75rem; align-items: center; margin-top: .75rem; }
        .resume-update-progress progress { flex: 1; max-width: 24rem; height: .75rem; }
        .resume-update-problem { color: rgb(220 38 38); }
        .resume-update-ok { color: rgb(22 163 74); font-weight: 600; }
        .resume-update-problems { margin: .5rem 0 0; padding-left: 1.25rem; list-style: disc; }
        .resume-update-note { margin-top: 1rem; }
        .resume-update-notes { margin: 0 0 .75rem; }
        .resume-update-notes summary, .resume-update-manual summary { cursor: pointer; font-weight: 600; }
        .resume-update-manual { margin-top: 1.25rem; }
        .resume-update-markdown { margin-top: .5rem; font-size: .9rem; }
        .resume-update-markdown ul { padding-left: 1.25rem; list-style: disc; }
        .resume-update-markdown a, .resume-link { text-decoration: underline; }
        .resume-update-packages { margin: 0; padding: 0; list-style: none; }
        .resume-update-packages li { display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; padding: .35rem 0; }
        .resume-update-check p { margin: .75rem 0 0; }
        .resume-cron-command { margin-top: .35rem; padding: .5rem .75rem; border-radius: .5rem; background: rgb(0 0 0 / .05); user-select: all; }
        .dark .resume-cron-command { background: rgb(255 255 255 / .06); }
        .resume-cron-jobs { width: 100%; font-size: .9rem; border-collapse: collapse; }
        .resume-cron-jobs th { text-align: left; font-weight: 600; padding: .35rem .75rem .35rem 0; }
        .resume-cron-jobs td { padding: .35rem .75rem .35rem 0; vertical-align: top; word-break: break-word; }
        .resume-cron-tag { margin-left: .35rem; padding: 0 .4rem; border-radius: .25rem; font-size: .75rem; background: rgb(0 0 0 / .06); }
        .dark .resume-cron-tag { background: rgb(255 255 255 / .1); }
    </style>
</x-filament-panels::page>
