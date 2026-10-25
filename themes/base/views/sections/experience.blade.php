<section id="experience" class="cv-section" aria-labelledby="experience-title">
    @include('theme::partials.section-heading', ['key' => 'experience', 'icon' => 'briefcase'])
    @include('theme::partials.experience-timeline', ['experiences' => $cv->experiences()])
</section>
