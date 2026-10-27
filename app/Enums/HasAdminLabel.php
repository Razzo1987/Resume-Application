<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum labels for the admin: lang/{it,en}/admin.php → enums.{group}.{value}.
 *
 * @phpstan-require-implements \BackedEnum
 */
trait HasAdminLabel
{
    abstract protected static function labelGroup(): string;

    public function getLabel(): string
    {
        return __('admin.enums.'.static::labelGroup().'.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[(string) $case->value] = $case->getLabel();
        }

        return $options;
    }
}
