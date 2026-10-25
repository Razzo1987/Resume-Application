{{-- Pagina CV: le sezioni attive nell'ordine scelto in admin. Un tema può ridefinire ogni sezione. --}}
@extends('theme::layouts.site')

@section('content')
    @foreach ($cv->sections() as $section)
        @include('theme::sections.'.$section->key->value, [
            'section' => $section,
            'title' => $section->displayTitle(),
        ])
    @endforeach
@endsection
