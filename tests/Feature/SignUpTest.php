<?php

use App\Filament\App\Pages\Tenancy\RegisterWorkspace;
use App\Models\User;
use App\Models\Workspace;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Packstub\Teams\Filament\Pages\Auth\Register;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

it('shows the sign-up page', function () {
    get('/app/register')->assertOk();
});

it('creates a workspace for someone who signs up', function () {
    livewire(Register::class)
        ->fillForm([
            'name' => 'Nina Petrova',
            'email' => 'nina@example.com',
            'password' => 'a-long-password',
            'passwordConfirmation' => 'a-long-password',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $nina = User::where('email', 'nina@example.com')->sole();
    $workspace = $nina->joinedTeams()->sole();

    assertAuthenticatedAs($nina);

    expect($workspace->name)->toBe("Nina Petrova's Workspace")
        ->and($workspace->slug)->toBe('nina-petrovas-workspace')
        ->and($workspace->plan)->toBe('free')
        ->and($nina->ownsTeam($workspace))->toBeTrue()
        ->and($nina->is_admin)->toBeFalse();
});

it('opens the panel once the e-mail address is verified', function () {
    Notification::fake();

    livewire(Register::class)
        ->fillForm(['name' => 'Nina', 'email' => 'nina@example.com', 'password' => 'a-long-password', 'passwordConfirmation' => 'a-long-password'])
        ->call('register')
        ->assertHasNoFormErrors();

    $nina = User::where('email', 'nina@example.com')->sole();
    $workspace = $nina->joinedTeams()->sole();

    Notification::assertSentTo($nina, VerifyEmail::class);

    get("/app/{$workspace->slug}")->assertRedirect(route('filament.app.auth.email-verification.prompt'));

    // The link in the e-mail.
    get(Filament::getVerifyEmailUrl($nina))->assertRedirect();

    expect($nina->fresh()->hasVerifiedEmail())->toBeTrue();

    get('/app')->assertRedirect("/app/{$workspace->slug}");
    get("/app/{$workspace->slug}")->assertOk();
});

it('never makes an admin of someone who signs up', function () {
    livewire(Register::class)
        ->set('data.is_admin', true)
        ->fillForm(['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'a-long-password', 'passwordConfirmation' => 'a-long-password'])
        ->call('register');

    expect(User::where('email', 'eve@example.com')->sole()->is_admin)->toBeFalse();
});

it('keeps a long name within what a workspace name and slug may hold', function () {
    livewire(Register::class)
        ->fillForm(['name' => str_repeat('Bartholomew ', 21), 'email' => 'long@example.com', 'password' => 'a-long-password', 'passwordConfirmation' => 'a-long-password'])
        ->call('register')
        ->assertHasNoFormErrors();

    $workspace = User::where('email', 'long@example.com')->sole()->joinedTeams()->sole();

    expect(strlen($workspace->name))->toBeLessThanOrEqual(Workspace::NAME_MAX_LENGTH)
        ->and(strlen($workspace->slug))->toBeLessThanOrEqual(Workspace::SLUG_MAX_LENGTH)
        ->and($workspace->slug)->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*$/');

    // The same name again gets a slug of its own, within the same maximum.
    $second = Workspace::uniqueSlug($workspace->name);

    expect($second)->not->toBe($workspace->slug)
        ->and(strlen($second))->toBeLessThanOrEqual(Workspace::SLUG_MAX_LENGTH);
});

it('gives two people with the same name a workspace each', function () {
    $signUp = function (string $email): string {
        livewire(Register::class)
            ->fillForm(['name' => 'Sam', 'email' => $email, 'password' => 'a-long-password', 'passwordConfirmation' => 'a-long-password'])
            ->call('register')
            ->assertHasNoFormErrors();

        auth()->logout();

        return User::where('email', $email)->sole()->joinedTeams()->sole()->slug;
    };

    expect($signUp('one@example.com'))->toBe('sams-workspace')
        ->and($signUp('two@example.com'))->toStartWith('sams-workspace-');
});

it('refuses a second account for the same e-mail address', function () {
    User::factory()->create(['email' => 'nina@example.com']);

    livewire(Register::class)
        ->fillForm(['name' => 'Nina', 'email' => 'nina@example.com', 'password' => 'a-long-password', 'passwordConfirmation' => 'a-long-password'])
        ->call('register')
        ->assertHasFormErrors(['email']);

    expect(User::query()->count())->toBe(1)
        ->and(Workspace::query()->count())->toBe(0);
});

it('asks an unverified account to verify before it creates a workspace', function () {
    actingAs(User::factory()->unverified()->create())
        ->get('/app/new')
        ->assertRedirect(route('filament.app.auth.email-verification.prompt'));
});

it('lets someone create another workspace and makes them its owner', function () {
    $user = User::factory()->create();

    actingAs($user);

    livewire(RegisterWorkspace::class)
        ->fillForm(['name' => 'Side Project'])
        ->call('register')
        ->assertHasNoFormErrors();

    $workspace = $user->joinedTeams()->sole();

    expect($workspace->name)->toBe('Side Project')
        ->and($user->ownsTeam($workspace))->toBeTrue();

    livewire(RegisterWorkspace::class)
        ->fillForm(['name' => str_repeat('x', Workspace::NAME_MAX_LENGTH + 1)])
        ->call('register')
        ->assertHasFormErrors(['name']);
});

it('sends someone without a workspace to create one', function () {
    actingAs(User::factory()->create())->get('/app')->assertRedirect('/app/new');
});

it('keeps people out of workspaces they do not belong to', function (string $page) {
    $workspace = workspace();

    actingAs(User::factory()->create())
        ->get("/app/{$workspace->slug}{$page}")
        ->assertNotFound();
})->with(['', '/projects', '/members', '/invitations', '/plan', '/billing', '/profile']);

it('sends a guest to the sign-in page', function (string $page) {
    $workspace = workspace();

    get("/app/{$workspace->slug}{$page}")->assertRedirect('/app/login');
})->with(['', '/projects', '/members', '/invitations', '/plan', '/billing', '/profile']);

it('opens every page of a workspace to its members', function (string $page) {
    $workspace = workspace();

    actingAs(memberOf($workspace, 'member'))
        ->get("/app/{$workspace->slug}{$page}")
        ->assertOk();
})->with(['', '/projects', '/members', '/plan']);
