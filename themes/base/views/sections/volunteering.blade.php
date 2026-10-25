<section id="volunteering" class="cv-section" aria-labelledby="volunteering-title">
    @include('theme::partials.section-heading', ['key' => 'volunteering', 'icon' => 'volunteering'])
    @include('theme::partials.experience-timeline', ['experiences' => $cv->volunteering()])
</section>
