{{-- Qualifications below name and photo: $layout->headlines --}}
@if ($layout->headlines)
    <ul class="profile-headlines">
        @foreach ($layout->headlines as $headline)
            <li>{{ $headline }}</li>
        @endforeach
    </ul>
@endif
