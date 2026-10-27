{{-- CV page: the enabled sections in the order chosen in the admin. A theme can redefine every section. --}}
@extends('theme::layouts.site')

@section('content')
    @foreach ($cv->sections() as $section)
        @include('theme::sections.'.$section->key->value, [
            'section' => $section,
            'title' => $section->displayTitle(),
        ])
    @endforeach
@endsection
