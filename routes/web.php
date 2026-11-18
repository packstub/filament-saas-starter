<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PersonalDataExportController;
use App\Http\Controllers\WorkspaceDataExportController;
use Illuminate\Support\Facades\Route;

// The public site: the home page, the legal pages and what search engines read.
// The app itself is the Filament panel at /app, the back office the one at /admin;
// both register their own routes.
Route::get('/', HomeController::class)->name('home');
Route::view('/terms', 'site.terms')->name('terms');
Route::view('/privacy', 'site.privacy')->name('privacy');

// The data exports (App\Support\DataExport), streamed straight to the browser.
// The Profile and Workspace settings pages link here.
Route::middleware('auth')->prefix('exports')->name('exports.')->group(function () {
    Route::get('me', PersonalDataExportController::class)->name('personal');
    Route::get('workspaces/{workspace:slug}', WorkspaceDataExportController::class)
        ->can('export', 'workspace')
        ->name('workspace');
});

// Every public page, for search engines. Add a route name here when you add a page.
Route::get('/sitemap.xml', fn () => response()
    ->view('site.sitemap', ['urls' => collect(['home', 'terms', 'privacy'])->map(fn (string $name): string => route($name))])
    ->header('Content-Type', 'application/xml'))
    ->name('sitemap');

// Search engines may read the site and the sign-up pages, never the panels. Outside
// production (a staging copy, say) they are asked to stay away altogether.
Route::get('/robots.txt', fn () => response(implode("\n", app()->isProduction()
    ? ['User-agent: *', 'Allow: /app/login', 'Allow: /app/register', 'Disallow: /app/', 'Disallow: /admin', 'Disallow: /exports/', '', 'Sitemap: '.route('sitemap')]
    : ['User-agent: *', 'Disallow: /'])."\n")
    ->header('Content-Type', 'text/plain'))
    ->name('robots');
