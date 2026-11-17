<?php

use Illuminate\Support\Facades\Schedule;

it('runs the housekeeping of the plugins every day', function (string $command) {
    $event = collect(Schedule::events())->first(fn ($event): bool => str_contains($event->command, $command));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *');
})->with(['teams:prune-invitations', 'features:prune']);
