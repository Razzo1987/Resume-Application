<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Themes\ThemeManager;
use Illuminate\View\Component;

/**
 * Icona semantica: <x-theme-icon name="briefcase" />.
 * Il nome è indipendente dal set di icone; ogni tema lo traduce nel proprio (icons.php).
 * Con lo sprite SVG del tema compilato si usa <svg><use>, altrimenti le classi del font di icone.
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
