<footer class="site-footer">
    <p>© {{ now()->year }} {{ $layout->profile->full_name }}</p>
    @if ($layout->lastUpdated)
        <p><time datetime="{{ $layout->lastUpdatedMachine }}">{{ __('site.footer.last_updated', ['date' => $layout->lastUpdated]) }}</time></p>
    @endif
    @if ($layout->gdprText)
        <p class="footer-gdpr">{!! nl2br(e($layout->gdprText)) !!}</p>
    @endif
    <p class="footer-credits"><a href="{{ config('resume.project_url') }}" rel="noopener">{{ __('site.footer.made_with') }}</a></p>
</footer>
