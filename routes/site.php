<?php

/*
 * Rotte del sito pubblico.
 * Gruppo middleware "site": niente sessione, niente CSRF, niente cookie (vedi bootstrap/app.php).
 *
 * Gli indirizzi dipendono dalle lingue e dai segmenti tradotti scelti in admin, quindi le rotte sono generiche
 * e i controller verificano che l'URL sia quello canonico (altrimenti 301).
 */

use App\Http\Controllers\Site\BlogController;
use App\Http\Controllers\Site\CvController;
use App\Http\Controllers\Site\DetailController;
use App\Http\Controllers\Site\RobotsController;
use App\Http\Controllers\Site\RootController;
use App\Http\Controllers\Site\SegmentController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Middleware\ResolveSiteLocale;
use Illuminate\Support\Facades\Route;

$locale = '[A-Za-z]{2,3}-[A-Za-z]{2}';
$word = '[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*';

Route::get('/', RootController::class)->name('site.root');
Route::get('/sitemap.xml', SitemapController::class)->name('site.sitemap');
Route::get('/robots.txt', RobotsController::class)->name('site.robots');

// "/it" → "/it-IT"
Route::get('/{language}', [RootController::class, 'language'])->where('language', '[A-Za-z]{2,3}')->name('site.language');

Route::middleware(ResolveSiteLocale::class)->group(function () use ($locale, $word): void {
    Route::get('/{locale}', CvController::class)
        ->where('locale', $locale)
        ->name('site.cv');

    Route::get('/{locale}/feed.xml', [BlogController::class, 'feed'])
        ->where('locale', $locale)
        ->name('site.feed');

    Route::get('/{locale}/{segment}', SegmentController::class)
        ->where(['locale' => $locale, 'segment' => $word])
        ->name('site.segment');

    Route::get('/{locale}/{segment}/{slug}', DetailController::class)
        ->where(['locale' => $locale, 'segment' => $word, 'slug' => $word])
        ->name('site.detail');
});
