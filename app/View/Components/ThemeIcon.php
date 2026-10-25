<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Themes\ThemeManager;
use Illuminate\View\Component;

/**
 * Icona semantica: <x-theme-icon name="briefcase" />.
 * Il nome è indipendente dal set di icone; ogni tema lo traduce nel proprio (icons.php).
 */
class ThemeIcon extends Component
{
    public ?string $classes;

    public function __construct(ThemeManager $themes, public string $name)
    {
        $this->classes = $themes->icon($name);
    }

    public function shouldRender(): bool
    {
        return $this->classes !== null;
    }

    public function render(): string
    {
        return <<<'BLADE'
            <i {{ $attributes->class([$classes])->merge(['aria-hidden' => 'true']) }}></i>
            BLADE;
    }
}
