<?php

use Illuminate\Support\Facades\Schedule;

it('runs the housekeeping of the plugins, the audit log and expired API tokens every day', function (string $command) {
    $event = collect(Schedule::events())->first(fn ($event): bool => str_contains($event->command, $command));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *');
})->with(['teams:prune-invitations', 'features:prune', 'model:prune', 'sanctum:prune-expired']);
