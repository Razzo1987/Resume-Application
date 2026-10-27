<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Common European Framework of Reference for Languages levels (QCER / CEFR).
 */
enum CefrLevel: string implements HasLabel
{
    case A1 = 'A1';
    case A2 = 'A2';
    case B1 = 'B1';
    case B2 = 'B2';
    case C1 = 'C1';
    case C2 = 'C2';

    public function getLabel(): string
    {
        return __('admin.enums.cefr.'.$this->value);
    }

    /**
     * Position on the scale (1–6), for the level bars.
     */
    public function rank(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}
