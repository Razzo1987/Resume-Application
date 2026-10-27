<?php

declare(strict_types=1);

namespace App\Themes;

use RuntimeException;

/**
 * Error while installing or removing a theme, with a message to show to the administrator.
 */
class ThemeException extends RuntimeException {}
