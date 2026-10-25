@if ($files === [])
    <p>{{ __('admin.system.no_orphans') }}</p>
@else
    <p>{{ __('admin.system.orphans_help', ['count' => count($files)]) }}</p>
    <ul style="max-height: 18rem; overflow: auto; font-family: monospace; font-size: .85rem; margin-top: .75rem">
        @foreach ($files as $file)
            <li>{{ $file }}</li>
        @endforeach
    </ul>
@endif
