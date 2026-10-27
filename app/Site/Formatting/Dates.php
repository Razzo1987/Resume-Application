<?php

declare(strict_types=1);

namespace App\Site\Formatting;

use App\Support\Settings\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Dates and durations in the page language: "gen 2021 - in corso", "2 anni e 3 mesi".
 */
final class Dates
{
    public function monthYear(CarbonInterface $date): string
    {
        return $date->copy()->locale($this->carbonLocale())->isoFormat('MMM YYYY');
    }

    public function day(CarbonInterface $date): string
    {
        return $date->copy()->locale($this->carbonLocale())->isoFormat('LL');
    }

    /**
     * Day of a moment (e.g. the publication of an article, stored in UTC) in the time zone of the site.
     * Not for plain dates (start and end of an experience): they have no time and must not move.
     */
    public function localDay(CarbonInterface $moment): string
    {
        return $this->day($moment->copy()->setTimezone(app(Settings::class)->timezone()));
    }

    /**
     * Period "start - end". Without an end: "in corso" (if there is a start) or just the start date.
     */
    public function period(?CarbonInterface $start, ?CarbonInterface $end, bool $ongoingIfOpen = true): string
    {
        if ($start === null) {
            return $end !== null ? $this->monthYear($end) : '';
        }

        if ($end === null) {
            return $ongoingIfOpen
                ? $this->monthYear($start).' - '.__('site.time.present')
                : $this->monthYear($start);
        }

        if ($start->format('Y-m') === $end->format('Y-m')) {
            return $this->monthYear($start);
        }

        return $this->monthYear($start).' - '.$this->monthYear($end);
    }

    /**
     * Duration in years and months, counting both the start and end months (Jan–Mar = 3 months).
     */
    public function duration(?CarbonInterface $start, ?CarbonInterface $end = null): ?string
    {
        if ($start === null) {
            return null;
        }

        $end ??= Carbon::now();
        $months = ((int) $end->format('Y') * 12 + (int) $end->format('n')) - ((int) $start->format('Y') * 12 + (int) $start->format('n')) + 1;

        if ($months < 1) {
            return __('site.time.less_than_a_month');
        }

        $years = intdiv($months, 12);
        $rest = $months % 12;
        $parts = [];

        if ($years > 0) {
            $parts[] = trans_choice('site.time.years', $years, ['count' => $years]);
        }

        if ($rest > 0) {
            $parts[] = trans_choice('site.time.months', $rest, ['count' => $rest]);
        }

        return implode(' '.__('site.time.and').' ', $parts);
    }

    /**
     * Value for the datetime attribute of <time> (year-month).
     */
    public function machine(?CarbonInterface $date, bool $withDay = false): ?string
    {
        return $date?->format($withDay ? 'Y-m-d' : 'Y-m');
    }

    private function carbonLocale(): string
    {
        return str_replace('-', '_', app()->getLocale());
    }
}
