<?php

use App\Site\Formatting\Dates;
use Carbon\Carbon;

beforeEach(fn () => Carbon::setTestNow('2026-10-25'));

it('formats periods in the current language', function (string $locale, string $closed, string $open) {
    app()->setLocale($locale);
    $dates = app(Dates::class);

    expect($dates->period(Carbon::parse('2019-03-01'), Carbon::parse('2020-12-31')))->toBe($closed)
        ->and($dates->period(Carbon::parse('2021-01-01'), null))->toBe($open);
})->with([
    ['it-IT', 'mar 2019 - dic 2020', 'gen 2021 - in corso'],
    ['en-GB', 'Mar 2019 - Dec 2020', 'Jan 2021 - present'],
    ['de-DE', 'Mär 2019 - Dez 2020', 'Jan 2021 - heute'],
]);

it('counts durations including the first and last month', function () {
    app()->setLocale('it-IT');
    $dates = app(Dates::class);

    expect($dates->duration(Carbon::parse('2022-01-01'), Carbon::parse('2022-03-31')))->toBe('3 mesi')
        ->and($dates->duration(Carbon::parse('2022-01-01'), Carbon::parse('2022-12-31')))->toBe('1 anno')
        ->and($dates->duration(Carbon::parse('2021-01-01')))->toBe('5 anni e 10 mesi')
        ->and($dates->duration(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-20')))->toBe('1 mese');
});

it('shows a single month once', function () {
    app()->setLocale('en-GB');

    expect(app(Dates::class)->period(Carbon::parse('2024-05-01'), Carbon::parse('2024-05-31')))->toBe('May 2024');
});
