<?php

/*
 * Installer web: disponibile solo finché manca il file .env (vedi RedirectToInstaller).
 * Nessuna sessione né cookie: non esiste ancora una chiave dell'applicazione.
 */

use App\Http\Controllers\Install\InstallController;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallController::class, 'show'])->name('install');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');
