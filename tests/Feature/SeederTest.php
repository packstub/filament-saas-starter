<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Packstub\Teams\Facades\Teams;

use function Pest\Laravel\artisan;
use function Pest\Laravel\seed;

it('seeds the demo once, however often it runs', function () {
    seed();
    seed();

    $acme = Workspace::query()->sole();
    $owner = User::query()->where('email', DatabaseSeeder::OWNER_EMAIL)->sole();
    $admin = User::query()->where('email', DatabaseSeeder::ADMIN_EMAIL)->sole();

    expect(User::query()->count())->toBe(3)
        ->and(Project::query()->count())->toBe(3)
        ->and($acme->pendingInvitations()->count())->toBe(1)
        ->and($acme->plan)->toBe('pro')
        ->and($owner->ownsTeam($acme))->toBeTrue()
        ->and($owner->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check(DatabaseSeeder::PASSWORD, $owner->password))->toBeTrue()
        ->and(Teams::membersOf($acme)->count())->toBe(2)
        ->and($admin->is_admin)->toBeTrue()
        ->and(User::query()->where('is_admin', true)->count())->toBe(1);
});

it('never seeds the demo accounts in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    artisan('db:seed', ['--force' => true])
        ->expectsOutputToContain('not seeded in production')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0)
        ->and(Workspace::query()->count())->toBe(0);
});
