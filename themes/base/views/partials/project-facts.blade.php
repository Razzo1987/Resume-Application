{{-- Cliente, settore e ruolo di un progetto: $project --}}
@php
    $facts = array_filter([
        'client' => $project->client?->name,
        'sector' => $project->t('sector'),
        'role' => $project->t('role'),
    ]);
@endphp
@if ($facts)
    <dl class="facts">
        @foreach ($facts as $fact => $value)
            <div class="fact">
                <dt>{{ __('site.experience.'.$fact) }}</dt>
                <dd>{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
@endif
