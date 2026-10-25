<?php

declare(strict_types=1);

namespace App\Site\Formatting;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Date e durate nella lingua della pagina: "gen 2021 - in corso", "2 anni e 3 mesi".
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
     * Periodo "inizio - fine". Senza fine: "in corso" (se c'è un inizio) oppure solo la data di inizio.
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
     * Durata in anni e mesi, contando i mesi di inizio e fine (gen–mar = 3 mesi).
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
     * Valore per l'attributo datetime di <time> (anno-mese).
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
