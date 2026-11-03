<x-filament-panels::page>
    {{-- What can be updated: the app and every installed theme, with state, source and key --}}
    <x-filament::section icon="heroicon-o-squares-2x2">
        <x-slot name="heading">{{ __('admin.updates.sources') }}</x-slot>
        <x-slot name="description">
            {{ $checkedAt ? __('admin.updates.checked', ['when' => $checkedAt->diffForHumans()]) : __('admin.updates.never_checked') }}
            {{ $cronActive ? __('admin.updates.checked_cron') : __('admin.updates.checked_admin') }}
        </x-slot>

        <div class="resume-updates-table-wrap">
            <table class="resume-updates-table">
                <thead>
                    <tr>
                        <th>{{ __('admin.updates.columns.component') }}</th>
                        <th>{{ __('admin.updates.columns.installed') }}</th>
                        <th>{{ __('admin.updates.columns.latest') }}</th>
                        <th>{{ __('admin.updates.columns.source') }}</th>
                        <th>{{ __('admin.updates.columns.state') }}</th>
                        <th aria-label="{{ __('admin.updates.columns.action') }}"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sources as $source)
                        <tr @class(['is-problem' => $source->hasProblem()])>
                            <td>
                                <strong>{{ $source->title }}</strong>
                                <span class="resume-updates-type">
                                    {{ $source->isApp() ? __('admin.updates.type.app') : __('admin.updates.type.theme') }}@if ($source->active) · {{ __('admin.themes.active') }}@endif
                                </span>
                            </td>
                            <td>{{ $source->installed }}</td>
                            <td>
                                @if ($source->latest)
                                    @if ($source->latest->url)
                                        <a href="{{ $source->latest->url }}" target="_blank" rel="noopener" class="resume-link">{{ $source->latest->version }}</a>
                                    @else
                                        {{ $source->latest->version }}
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>@include('filament.pages.partials.update-access', ['source' => $source])</td>
                            <td>@include('filament.pages.partials.update-state', ['source' => $source])</td>
                            <td><div class="resume-updates-action">
                                @if ($source->hasUpdate() && $source->isApp())
                                    <x-filament::button size="sm" icon="heroicon-o-arrow-down-circle" tag="a" href="#resume-app">{{ __('admin.updates.go_to_app') }}</x-filament::button>
                                @elseif ($source->hasUpdate())
                                    {{ ($this->updateThemeAction)(['theme' => $source->id, 'version' => $source->latest->version]) }}
                                @elseif ($source->needsKey() && $source->key && $source->key->isEditable())
                                    {{ ($this->keyAction)(['key' => $source->key->id]) }}
                                @elseif ($source->needsKey() && $source->key)
                                    <span class="resume-updates-message">{{ __('admin.updates.keys.env_change') }}</span>
                                @elseif ($source->needsKey())
                                    {{ ($this->keyAction)(['scope' => strtok((string) $source->repository, '/').'/*']) }}
                                @endif
                            </div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- The app: new version, packages on the server, automatic updates --}}
    <x-filament::section icon="heroicon-o-arrow-down-circle" id="resume-app">
        <x-slot name="heading">Resume App</x-slot>

        <dl class="resume-system-list">
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

        @if (! $packagedInstall)
            <p class="resume-update-note">{{ __('admin.updates.problems.not_packaged') }}</p>
        @else
            @if ($appRelease)
                <div class="resume-update-box" x-data="resumeUpdateDownload()">
                    <h3 class="resume-update-title">{{ __('admin.updates.available', ['version' => $appRelease->version]) }}</h3>
                    @if ($appNotes)
                        <details class="resume-update-notes">
                            <summary>{{ __('admin.updates.notes_title') }}</summary>
                            <div class="resume-update-markdown">{{ $appNotes }}</div>
                        </details>
                    @endif
                    @if ($appRelease->package)
                        <x-filament::button icon="heroicon-o-cloud-arrow-down" x-on:click="download()" x-bind:disabled="busy">
                            {{ __('admin.updates.download_install', ['size' => \Illuminate\Support\Number::fileSize($appRelease->package['size'], 1)]) }}
                        </x-filament::button>
                        <div class="resume-update-progress" x-show="total > 0" x-cloak>
                            <progress x-bind:max="total" x-bind:value="received" aria-label="{{ __('admin.updates.download_progress') }}"></progress>
                            <span x-text="Math.round(received / total * 100) + '%'"></span>
                        </div>
                        <p class="resume-update-problem" x-show="error" x-text="error" x-cloak></p>
                    @else
                        <p class="resume-update-problem">{{ __('admin.updates.errors.no_package', ['version' => $appRelease->version]) }}</p>
                    @endif
                </div>
            @endif

            @if ($packages)
                <div class="resume-update-box">
                    <h3 class="resume-update-title">{{ __('admin.updates.packages') }}</h3>
                    <ul class="resume-update-packages">
                        @foreach ($packages as $file => $label)
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

    {{-- GitHub keys: only for private repositories --}}
    <x-filament::section icon="heroicon-o-key" id="keys">
        <x-slot name="heading">{{ __('admin.updates.keys.title') }}</x-slot>
        <x-slot name="description">{{ __('admin.updates.keys.intro') }}</x-slot>
        <x-slot name="afterHeader">{{ ($this->keyAction)([]) }}</x-slot>

        @if ($keys === [])
            <p class="resume-updates-empty">{{ __('admin.updates.keys.none') }}</p>
        @else
            <div class="resume-updates-table-wrap">
                <table class="resume-updates-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.updates.keys.label') }}</th>
                            <th>{{ __('admin.updates.keys.scopes') }}</th>
                            <th>{{ __('admin.updates.keys.used_by') }}</th>
                            <th>{{ __('admin.updates.columns.state') }}</th>
                            <th aria-label="{{ __('admin.updates.columns.action') }}"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($keys as $key)
                            @php($state = $key->state())
                            <tr @class(['is-problem' => in_array($state, ['expired', 'invalid'], true)])>
                                <td>
                                    <strong>{{ $key->label }}</strong>
                                    <span class="resume-updates-type">{{ $key->hint() }}@if ($key->login) · {{ $key->login }}@endif</span>
                                </td>
                                <td>
                                    @foreach ($key->scopes as $scope)
                                        <code>{{ $scope === '*' ? __('admin.updates.keys.every') : $scope }}</code>@unless ($loop->last), @endunless
                                    @endforeach
                                </td>
                                <td>{{ isset($keyUse[$key->id]) ? implode(', ', $keyUse[$key->id]) : __('admin.updates.keys.unused') }}</td>
                                <td>
                                    <x-filament::badge :color="match ($state) { 'ok' => 'success', 'expiring' => 'warning', 'expired', 'invalid' => 'danger', default => 'gray' }">
                                        {{ __('admin.updates.keys.states.'.$state) }}
                                    </x-filament::badge>
                                    <span class="resume-updates-message">
                                        @if ($key->expiresAt)
                                            {{ __('admin.updates.keys.expires', ['date' => $key->expiresAt->translatedFormat('j F Y')]) }}
                                        @elseif ($key->status === 'ok')
                                            {{ __('admin.updates.keys.no_expiration') }}
                                        @endif
                                    </span>
                                </td>
                                <td><div class="resume-updates-action">
                                    {{ ($this->verifyKeyAction)(['key' => $key->id]) }}
                                    @if ($key->isEditable())
                                        {{ ($this->keyAction)(['key' => $key->id]) }}
                                        {{ ($this->deleteKeyAction)(['key' => $key->id]) }}
                                    @endif
                                </div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <script>
        // Chunked download from GitHub (each chunk is a short request), then checks and installation
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
    </script>

    <style>
        .resume-updates-table-wrap { overflow-x: auto; margin: -.25rem 0; }
        .resume-updates-table { width: 100%; font-size: .875rem; border-collapse: collapse; }
        .resume-updates-table th { text-align: left; font-weight: 600; padding: .5rem .75rem .5rem 0; white-space: nowrap; }
        .resume-updates-table td { padding: .65rem .75rem .65rem 0; vertical-align: top; border-top: 1px solid rgb(0 0 0 / .06); }
        .dark .resume-updates-table td { border-top-color: rgb(255 255 255 / .08); }
        .resume-updates-table tr.is-problem td:first-child { box-shadow: inset 3px 0 0 rgb(220 38 38); padding-left: .65rem; }
        .resume-updates-type, .resume-updates-access, .resume-updates-message { display: block; margin-top: .2rem; font-size: .8125rem; opacity: .75; }
        .resume-updates-message { max-width: 22rem; }
        .resume-updates-action { display: flex; justify-content: flex-end; align-items: center; gap: .5rem; white-space: nowrap; }
        .resume-updates-action .resume-updates-message { white-space: normal; text-align: right; }
        .resume-updates-empty { margin: 0; opacity: .8; }
        .resume-system-list { display: grid; grid-template-columns: minmax(9rem, max-content) 1fr; gap: .5rem 1.5rem; margin: 0; }
        .resume-system-list dt { font-weight: 600; }
        .resume-system-list dd { margin: 0; }
        .resume-system-steps { margin: 0; padding-left: 1.25rem; list-style: decimal; }
        .resume-system-steps li { margin: .35rem 0; }
        .resume-update-box { margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid rgb(0 0 0 / .08); }
        .dark .resume-update-box { border-top-color: rgb(255 255 255 / .1); }
        .resume-update-title { margin: 0 0 .5rem; font-weight: 600; }
        .resume-update-box p { margin: 0 0 .75rem; }
        .resume-update-progress { display: flex; gap: .75rem; align-items: center; margin-top: .75rem; }
        .resume-update-progress progress { flex: 1; max-width: 24rem; height: .75rem; }
        .resume-update-problem { color: rgb(220 38 38); }
        .resume-update-note { margin-top: 1rem; }
        .resume-update-notes { margin: 0 0 .75rem; }
        .resume-update-notes summary, .resume-update-manual summary { cursor: pointer; font-weight: 600; }
        .resume-update-manual { margin-top: 1.25rem; }
        .resume-update-markdown { margin-top: .5rem; font-size: .9rem; }
        .resume-update-markdown ul { padding-left: 1.25rem; list-style: disc; }
        .resume-update-markdown a, .resume-link { text-decoration: underline; }
        .resume-update-packages { margin: 0; padding: 0; list-style: none; }
        .resume-update-packages li { display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; padding: .35rem 0; }
    </style>
</x-filament-panels::page>
