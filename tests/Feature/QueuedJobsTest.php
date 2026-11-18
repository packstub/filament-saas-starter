<?php

use App\Jobs\SyncSeatQuantity;
use App\Jobs\SyncStripeCustomer;
use Illuminate\Support\Facades\DB;
use Packstub\Teams\Facades\Teams;

/**
 * The commands waiting in the `jobs` table, exactly as the queue stored them.
 *
 * @return list<string>
 */
function queuedCommands(): array
{
    return DB::table('jobs')->pluck('payload')
        ->map(fn (string $payload): string => json_decode($payload, true)['data']['command'])
        ->all();
}

it('never writes a person\'s password, remember token or two-factor secret into the queue', function () {
    // A real queue, so jobs are serialized and stored as they would be in production.
    config()->set('queue.default', 'database');
    config()->set('billing.plans.pro.per_seat', true);

    $workspace = workspace('pro');
    $workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    $owner = Teams::ownersOf($workspace)->first();
    $owner->forceFill(['remember_token' => 'remember-me-token-123'])->save();
    $owner->saveAppAuthenticationSecret('TOTPSECRETTOTPSECRET');

    // Every membership event a queued job follows: joining, a transfer, leaving.
    $member = memberOf($workspace, 'member');
    Teams::transferOwnership($workspace, $owner->fresh(), $member);
    Teams::removeMember($workspace, $owner->fresh());

    $commands = queuedCommands();

    expect($commands)->not->toBeEmpty();

    foreach ($commands as $command) {
        expect($command)
            ->not->toContain($owner->fresh()->password)
            ->not->toContain('remember-me-token-123')
            ->not->toContain('remember_token')
            ->not->toContain('password')
            ->not->toContain('app_authentication')
            ->not->toContain($owner->email)
            ->not->toContain($member->email);
    }

    // Only the workspace's id travels: the jobs read the rest when they run.
    expect(DB::table('jobs')->pluck('payload')->map(fn (string $payload): string => json_decode($payload, true)['displayName'])->unique()->sort()->values()->all())
        ->toBe([SyncSeatQuantity::class, SyncStripeCustomer::class]);
});

it('drops a queued job whose workspace was deleted meanwhile', function () {
    config()->set('queue.default', 'database');
    config()->set('billing.plans.pro.per_seat', true);

    $workspace = workspace('pro');
    memberOf($workspace, 'member');
    $workspace->delete();

    // The worker's own memory limit (128 MB) would stop it once the whole suite has run in this process.
    $this->artisan('queue:work', ['--stop-when-empty' => true, '--memory' => 4096])->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});
