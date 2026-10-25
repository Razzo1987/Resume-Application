@php /** @var \App\Support\Update\PackageReport|null $report */ @endphp
<div class="resume-update-check">
    @if ($report === null)
        <p class="resume-update-problem">{{ __('admin.updates.problems.not_zip') }}</p>
    @else
        <dl class="resume-system-list">
            <dt>{{ __('admin.updates.version_change') }}</dt>
            <dd>{{ $report->currentVersion }} → <strong>{{ $report->version ?? '?' }}</strong></dd>
            <dt>{{ __('admin.updates.package') }}</dt>
            <dd>{{ trans_choice('admin.updates.package_files', $report->entries, ['count' => number_format($report->entries, 0, ',', '.')]) }}, {{ \Illuminate\Support\Number::fileSize($report->size, 1) }}</dd>
        </dl>

        @if ($report->ok())
            <p class="resume-update-ok">{{ __('admin.updates.checks_passed') }}</p>
            @foreach ($report->notes as $note)
                <p>{{ $note }}</p>
            @endforeach
            <p>{{ __('admin.updates.install_steps') }}</p>
            <ol class="resume-system-steps">
                <li>{{ __('admin.updates.install_step_1') }}</li>
                <li>{{ __('admin.updates.install_step_2') }}</li>
                <li>{{ __('admin.updates.install_step_3') }}</li>
                <li>{{ __('admin.updates.install_step_4') }}</li>
            </ol>
        @else
            <p class="resume-update-problem">{{ __('admin.updates.checks_failed') }}</p>
            <ul class="resume-update-problems">
                @foreach ($report->problems as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
