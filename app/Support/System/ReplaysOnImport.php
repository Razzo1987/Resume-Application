<?php

declare(strict_types=1);

namespace App\Support\System;

/**
 * Migrazione che cambia anche i dati (non solo la struttura): va ripetuta quando si importano contenuti esportati
 * da una versione precedente, che non l'aveva ancora eseguita (vedi Backup::restore()).
 */
interface ReplaysOnImport
{
    public function replay(): void;
}
