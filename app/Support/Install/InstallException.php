<?php

declare(strict_types=1);

namespace App\Support\Install;

use RuntimeException;

/**
 * Installation error with a message to show to the user.
 */
class InstallException extends RuntimeException {}
