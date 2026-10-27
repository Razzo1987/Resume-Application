<?php

declare(strict_types=1);

namespace App\Support\System;

/**
 * Migration that also changes the data (not just the structure): it must be run again when importing content
 * exported from a previous version that had not run it yet (see Backup::restore()).
 */
interface ReplaysOnImport
{
    public function replay(): void;
}
