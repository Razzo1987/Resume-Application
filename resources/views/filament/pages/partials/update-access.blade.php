{{-- Where an update source comes from: repository, public or private, key used. --}}
@if ($source->repository)
    <code>{{ $source->repository }}</code>
    <span class="resume-updates-access">
        {{ $source->access() === 'unknown' && $source->state === 'needs_key' ? __('admin.updates.access.private_or_missing') : __('admin.updates.access.'.$source->access()) }}
        @if ($source->access() !== 'public')
            · {{ $source->key ? __('admin.updates.access.key', ['key' => $source->key->label]) : __('admin.updates.access.no_key') }}
        @endif
        · {{ $source->signed ? __('admin.updates.access.signed') : __('admin.updates.access.unsigned') }}
    </span>
@else
    <span class="resume-updates-access">{{ __('admin.updates.access.manual') }}</span>
@endif
