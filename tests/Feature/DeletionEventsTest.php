<?php

use App\Events\AccountDeleting;
use App\Events\WorkspaceDeleting;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Packstub\Teams\Facades\Teams;

use function Pest\Laravel\postJson;

it('tells listeners which workspace is going, while its projects are still there', function () {
    $workspace = workspace();
    Project::factory()->count(2)->for($workspace)->create();
    $seen = [];

    Event::listen(function (WorkspaceDeleting $event) use (&$seen): void {
        $seen[] = [$event->workspace->id, $event->workspace->projects()->count(), DB::transactionLevel() > 0];
    });

    $workspace->delete();

    expect($seen)->toBe([[$workspace->id, 2, true]])
        ->and(Workspace::query()->whereKey($workspace->id)->exists())->toBeFalse();
});

it('keeps the workspace and everything it had when a listener fails', function () {
    $workspace = workspace('pro');
    Project::factory()->for($workspace)->create();

    Event::listen(fn (WorkspaceDeleting $event) => throw new RuntimeException('A module could not clean up.'));

    expect(fn () => $workspace->delete())->toThrow(RuntimeException::class);

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeTrue()
        ->and($workspace->projects()->count())->toBe(1);
});

it('asks nothing of Stripe when a listener stops the delete', function () {
    $workspace = workspace('free');
    $workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $workspace, 'price_pro'))->assertOk();
    $stripe = fakeStripe([]);

    Event::listen(fn (WorkspaceDeleting $event) => throw new RuntimeException('A module could not clean up.'));

    expect(fn () => $workspace->delete())->toThrow(RuntimeException::class);

    // The paid subscription is still running: the customer keeps what they pay for.
    expect($stripe->requests)->toBe([])
        ->and($workspace->currentSubscription())->not->toBeNull();
});

it('tells listeners whose account is going, after the workspaces only they used', function () {
    $owner = User::factory()->create();
    $own = workspace(owner: $owner);
    $shared = workspace();
    Teams::addMember($shared, $owner, 'member');
    $seen = [];

    Event::listen(function (AccountDeleting $event) use (&$seen, $own): void {
        $seen[] = [$event->user->id, Workspace::query()->whereKey($own->id)->exists(), DB::transactionLevel() > 0];
    });

    $owner->deleteAccount();

    expect($seen)->toBe([[$owner->id, false, true]])
        ->and(User::query()->whereKey($owner->id)->exists())->toBeFalse();
});

it('keeps the account when a listener fails', function () {
    $owner = User::factory()->create();
    $own = workspace(owner: $owner);

    Event::listen(fn (AccountDeleting $event) => throw new RuntimeException('A module could not clean up.'));

    expect(fn () => $owner->deleteAccount())->toThrow(RuntimeException::class);

    expect(User::query()->whereKey($owner->id)->exists())->toBeTrue()
        ->and(Workspace::query()->whereKey($own->id)->exists())->toBeTrue();
});

it('is listened to synchronously, never on the queue', function (string $event) {
    // A queued listener would run after the rows are gone, outside the transaction.
    collect(Event::getRawListeners()[$event] ?? [])
        ->filter(fn (mixed $listener): bool => is_string($listener) || is_array($listener))
        ->map(fn (string|array $listener): string => is_array($listener) ? $listener[0] : Str::before($listener, '@'))
        ->each(fn (string $listener) => expect(is_subclass_of($listener, ShouldQueue::class))
            ->toBeFalse("{$listener} is queued: listen to ".class_basename($event).' synchronously.'));

    expect(true)->toBeTrue();
})->with([WorkspaceDeleting::class, AccountDeleting::class]);
