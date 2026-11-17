<?php

use Illuminate\Support\Facades\Route;

// The public home page. The app itself is the Filament panel at /app, the
// back office the one at /admin; both register their own routes.
Route::view('/', 'welcome')->name('home');
