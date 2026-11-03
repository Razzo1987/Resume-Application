{{--
    Zip upload of the admin, the same everywhere (app and theme packages, content exports): drop the file on the
    area or click to choose it. The file is sent in 1 MB chunks (so the hosting's upload limit does not matter), then
    the Livewire method "call" of the page receives its name on the server. Script and styles: filament.upload-assets.
--}}
@props(['target' => 'package', 'call', 'label', 'hint' => null])

<div class="resume-upload" x-data="resumeUpload(@js(route('filament.admin.update-upload', ['target' => $target])), @js(csrf_token()), @js($call))">
    <label class="resume-upload-zone" x-bind:class="{ 'is-over': over, 'is-busy': busy, 'is-done': done, 'is-error': error }"
        x-on:dragover.prevent="over = ! busy" x-on:dragleave.prevent="over = false" x-on:drop.prevent="over = false; pick($event.dataTransfer.files)">
        <input type="file" accept=".zip,application/zip" class="resume-upload-input" x-ref="file" x-bind:disabled="busy"
            x-on:change="pick($event.target.files)" aria-label="{{ $label }}">
        <span class="resume-upload-text" x-show="! name">
            {{ __('admin.upload.drop') }} <span class="resume-upload-browse">{{ __('admin.upload.browse') }}</span>
        </span>
        <span class="resume-upload-file" x-show="name" x-cloak>
            <strong x-text="name"></strong> <span class="resume-upload-size" x-text="sizeLabel()"></span>
        </span>
        <span class="resume-upload-progress" x-show="busy || done" x-cloak>
            <progress x-bind:max="size || 1" x-bind:value="sent" aria-label="{{ $label }}"></progress>
            <span x-show="! checking && ! done" x-text="Math.round(sent / (size || 1) * 100) + '%'"></span>
            <span x-show="checking" x-cloak>{{ __('admin.upload.checking') }}</span>
            <span x-show="done" x-cloak>{{ __('admin.upload.done') }}</span>
        </span>
        <span class="resume-upload-error" x-show="error" x-cloak><span x-text="error"></span> · {{ __('admin.upload.retry') }}</span>
    </label>
    @if ($hint)
        <p class="resume-upload-hint">{{ $hint }}</p>
    @endif
</div>
