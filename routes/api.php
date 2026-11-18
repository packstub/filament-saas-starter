<?php

use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Middleware\EnsureWorkspaceHasApiAccess;
use Illuminate\Support\Facades\Route;

// Version 1 of the API, at /api/v1, for workspace API tokens (the API tokens page in the
// panel). A token belongs to a workspace and authenticates as it: `$request->user()` is the
// Workspace. Each route names the ability a token needs; the rate limit is `api`
// (AppServiceProvider), per token.
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', EnsureWorkspaceHasApiAccess::class, 'throttle:api'])
    ->group(function (): void {
        Route::get('projects', [ProjectController::class, 'index'])->middleware('abilities:projects:read')->name('projects.index');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->middleware('abilities:projects:read')->name('projects.show');
        Route::post('projects', [ProjectController::class, 'store'])->middleware('abilities:projects:write')->name('projects.store');
    });
