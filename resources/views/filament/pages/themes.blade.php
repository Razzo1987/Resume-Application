<x-filament-panels::page>
    @if ($configured !== $active)
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <x-slot name="heading">{{ __('admin.themes.fallback', ['theme' => $configured]) }}</x-slot>
            <p>{{ __('admin.themes.fallback_help') }}</p>
        </x-filament::section>
    @endif

    @if ($hasUpdatable)
        <p class="resume-themes-checked">{{ $updatesChecked ? __('admin.themes.updates.checked', ['when' => $updatesChecked->diffForHumans()]) : __('admin.themes.updates.never') }}</p>
    @endif

    <div class="resume-themes">
        @foreach ($themes as $item)
            @php($theme = $item['theme'])
            <x-filament::section>
                <x-slot name="heading">
                    {{ $theme->title }}
                    <span class="resume-themes-version">{{ $theme->version }}</span>
                </x-slot>
                <x-slot name="afterHeader">
                    @if ($theme->name === $active)
                        <x-filament::badge color="success" icon="heroicon-o-check">{{ __('admin.themes.active') }}</x-filament::badge>
                    @endif
                </x-slot>

                @if ($theme->description)
                    <p>{{ $theme->description }}</p>
                @endif

                @if ($item['update'])
                    <div class="resume-themes-update">
                        <x-filament::badge color="warning" icon="heroicon-o-arrow-down-circle">{{ __('admin.themes.updates.available', ['version' => $item['update']->version]) }}</x-filament::badge>
                        @if ($item['update']->url)
                            <a href="{{ $item['update']->url }}" target="_blank" rel="noopener" class="resume-link">{{ __('admin.themes.updates.notes') }}</a>
                        @endif
                        {{ ($this->updateAction)(['theme' => $theme->name, 'version' => $item['update']->version]) }}
                    </div>
                @endif

                @if ($theme->variants)
                    <div class="resume-themes-variants">
                        <span class="resume-themes-variants-label">{{ __('admin.themes.variants') }}</span>
                        @foreach ($theme->variants as $variant)
                            @if ($theme->name === $active && $variant->name === $activeVariant)
                                <x-filament::badge color="success" icon="heroicon-o-check">{{ $variant->title }}</x-filament::badge>
                            @elseif ($item['assets'])
                                <x-filament::button size="xs" color="gray" outlined
                                    wire:click="mountAction('activate', {{ \Illuminate\Support\Js::from(['theme' => $theme->name, 'variant' => $variant->name]) }})"
                                    :tooltip="$variant->description">{{ $variant->title }}</x-filament::button>
                            @else
                                <span>{{ $variant->title }}</span>
                            @endif
                        @endforeach
                    </div>
                @endif

                <dl class="resume-system-list">
                    <dt>{{ __('admin.themes.name') }}</dt>
                    <dd><code>{{ $theme->name }}</code></dd>
                    @unless ($item['base'])
                        <dt>{{ __('admin.themes.extends') }}</dt>
                        <dd>{{ $theme->extends ?? 'base' }}</dd>
                    @endunless
                    @if ($theme->requires)
                        <dt>{{ __('admin.themes.requires') }}</dt>
                        <dd>{{ $theme->requires }} @unless ($item['compatible']) — <strong>{{ __('admin.themes.incompatible') }}</strong> @endunless</dd>
                    @endif
                    @if ($theme->updateRepository)
                        <dt>{{ __('admin.themes.updates.source') }}</dt>
                        <dd>
                            <code>{{ $theme->updateRepository }}</code>
                            @if ($item['updateError']) <br><span class="resume-themes-problem">{{ $item['updateError'] }}</span> @endif
                        </dd>
                    @endif
                    @unless ($item['assets'])
                        <dt>{{ __('admin.themes.assets') }}</dt>
                        <dd><strong>{{ __('admin.themes.assets_missing') }}</strong></dd>
                    @endunless
                </dl>

                @if ($theme->name !== $active || ! $item['base'])
                    <div class="resume-themes-actions">
                        @if ($theme->name !== $active && $item['assets'])
                            {{ ($this->activateAction)(['theme' => $theme->name]) }}
                        @endif
                        @if (! $item['base'] && $theme->name !== $active && $theme->name !== $configured)
                            {{ ($this->removeAction)(['theme' => $theme->name]) }}
                        @endif
                    </div>
                @endif
            </x-filament::section>
        @endforeach
    </div>


    <script>
        // Theme packages are uploaded in 1 MB chunks, then installed (installUploaded)
        window.resumeThemeUpload = (url, token) => ({
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
                        const response = await fetch(url + (url.includes('?') ? '&' : '?') + query, {
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
                        if (data.done) { await this.$wire.installUploaded(data.file); }
                    }
                } catch (exception) {
                    this.error = exception.message;
                } finally {
                    this.busy = false;
                }
            },
        });
    </script>

    <style>
        .resume-theme-upload-row { display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; }
        .resume-theme-upload-progress { display: flex; gap: .75rem; align-items: center; margin-top: .75rem; }
        .resume-theme-upload-progress progress { flex: 1; height: .75rem; }
        .resume-theme-upload-problem { margin-top: .75rem; color: rgb(220 38 38); }
        .resume-themes-checked { margin: 0; font-size: .875rem; opacity: .75; }
        .resume-themes { display: grid; gap: 1.5rem; }
        .resume-themes-version { margin-left: .5rem; font-weight: 400; font-size: .875rem; opacity: .7; }
        .resume-system-list { display: grid; grid-template-columns: minmax(9rem, max-content) 1fr; gap: .5rem 1.5rem; margin: 1rem 0 0; }
        .resume-system-list dt { font-weight: 600; }
        .resume-system-list dd { margin: 0; }
        .resume-themes-actions { display: flex; gap: .75rem; margin-top: 1rem; }
        .resume-themes-update, .resume-themes-variants { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; margin-top: 1rem; }
        .resume-themes-variants-label { font-weight: 600; margin-right: .25rem; }
        .resume-themes-problem { color: var(--danger-600, #dc2626); }
        .resume-link { text-decoration: underline; }
    </style>
</x-filament-panels::page>
