<?php

use App\Http\Controllers\PersonalDataExportController;
use App\Http\Controllers\WorkspaceDataExportController;
use Illuminate\Support\Facades\Route;

// The public home page. The app itself is the Filament panel at /app, the
// back office the one at /admin; both register their own routes.
Route::view('/', 'welcome')->name('home');

// The data exports (App\Support\DataExport), streamed straight to the browser.
// The Profile and Workspace settings pages link here.
Route::middleware('auth')->prefix('exports')->name('exports.')->group(function () {
    Route::get('me', PersonalDataExportController::class)->name('personal');
    Route::get('workspaces/{workspace:slug}', WorkspaceDataExportController::class)
        ->can('export', 'workspace')
        ->name('workspace');
});
