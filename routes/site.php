<?php

/*
 * Rotte del sito pubblico.
 * Gruppo middleware "site": niente sessione, niente CSRF, niente cookie (vedi bootstrap/app.php).
 */

use App\Http\Controllers\Site\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('site.home');
