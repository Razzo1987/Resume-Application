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

    <x-filament::section>
        <x-slot name="heading">{{ __('admin.system.how_to_update') }}</x-slot>
        <ol class="resume-system-steps">
            <li>{{ __('admin.system.update_step_1') }}</li>
            <li>{{ __('admin.system.update_step_2') }}</li>
            <li>{{ __('admin.system.update_step_3') }}</li>
            <li>{{ __('admin.system.update_step_4') }}</li>
        </ol>
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
    </style>
</x-filament-panels::page>
