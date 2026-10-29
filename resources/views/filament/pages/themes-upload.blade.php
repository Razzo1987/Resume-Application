{{-- Themes: chunked upload of a theme package (1 MB at a time, so the hosting's upload limit does not matter). --}}
<div x-data="resumeThemeUpload(@js($uploadUrl), @js(csrf_token()))" class="resume-theme-upload">
    <div class="resume-theme-upload-row">
        <input type="file" accept=".zip,application/zip" x-ref="file" x-bind:disabled="busy" aria-label="{{ __('admin.themes.package') }}">
        <x-filament::button icon="heroicon-o-arrow-up-tray" x-on:click="upload()" x-bind:disabled="busy">
            {{ __('admin.themes.install_submit') }}
        </x-filament::button>
    </div>
    <div class="resume-theme-upload-progress" x-show="size > 0" x-cloak>
        <progress x-bind:max="size" x-bind:value="sent" aria-label="{{ __('admin.themes.package') }}"></progress>
        <span x-text="Math.round(sent / size * 100) + '%'"></span>
    </div>
    <p class="resume-theme-upload-problem" x-show="error" x-text="error" x-cloak></p>
</div>
