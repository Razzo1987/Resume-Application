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

    {{-- Summary: the updates are managed in the Updates page --}}
    <x-filament::section icon="heroicon-o-arrow-path-rounded-square">
        <x-slot name="heading">{{ __('admin.updates.title') }}</x-slot>
        <x-slot name="afterHeader">
            <x-filament::button size="sm" color="gray" tag="a" :href="\App\Filament\Pages\ManageUpdates::getUrl()">{{ __('admin.updates.open') }}</x-filament::button>
        </x-slot>
        <dl class="resume-system-list">
            @foreach ($sources as $source)
                <dt>{{ $source->title }} {{ $source->installed }}</dt>
                <dd>@include('filament.pages.partials.update-state', ['source' => $source])</dd>
            @endforeach
        </dl>
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
        .resume-updates-message { display: block; margin-top: .2rem; font-size: .8125rem; opacity: .75; }
        .resume-cron-command { margin-top: .35rem; padding: .5rem .75rem; border-radius: .5rem; background: rgb(0 0 0 / .05); user-select: all; }
        .dark .resume-cron-command { background: rgb(255 255 255 / .06); }
        .resume-cron-jobs { width: 100%; font-size: .9rem; border-collapse: collapse; }
        .resume-cron-jobs th { text-align: left; font-weight: 600; padding: .35rem .75rem .35rem 0; }
        .resume-cron-jobs td { padding: .35rem .75rem .35rem 0; vertical-align: top; word-break: break-word; }
        .resume-cron-tag { margin-left: .35rem; padding: 0 .4rem; border-radius: .25rem; font-size: .75rem; background: rgb(0 0 0 / .06); }
        .dark .resume-cron-tag { background: rgb(255 255 255 / .1); }
    </style>
</x-filament-panels::page>
