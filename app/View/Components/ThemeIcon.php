<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Themes\ThemeManager;
use Illuminate\View\Component;

/**
 * Semantic icon: <x-theme-icon name="briefcase" />.
 * The name does not depend on the icon set; each theme maps it to its own (icons.php).
 * With the theme's compiled SVG sprite <svg><use> is used, otherwise the icon font classes.
 */
class ThemeIcon extends Component
{
    public ?string $classes;

    public ?string $sprite;

    public function __construct(ThemeManager $themes, public string $name)
    {
        $this->classes = $themes->icon($name);
        $this->sprite = $themes->spriteUrl();
    }

    public function shouldRender(): bool
    {
        return $this->classes !== null;
    }

    public function render(): string
    {
        return <<<'BLADE'
            @if ($sprite)
            <svg {{ $attributes->class(['icon', 'icon-'.$name])->merge(['aria-hidden' => 'true', 'focusable' => 'false']) }}><use href="{{ $sprite }}#{{ $name }}"></use></svg>
            @else
            <i {{ $attributes->class([$classes])->merge(['aria-hidden' => 'true']) }}></i>
            @endif
            BLADE;
    }
}
