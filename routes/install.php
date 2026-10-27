<?php

/*
 * Web installer: available only while the .env file is missing (see RedirectToInstaller).
 * No session or cookies: there is no application key yet.
 */

use App\Http\Controllers\Install\InstallController;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallController::class, 'show'])->name('install');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');
