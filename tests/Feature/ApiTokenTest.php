<?php

use App\Enums\AuditEventType;
use App\Filament\App\Pages\ApiTokens;
use App\Models\ApiToken;
use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\RateLimiter;
use Packstub\Features\Facades\Features;
use Packstub\Teams\Facades\Teams;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Livewire\livewire;

/**
 * A plain-text token for the workspace, as the API tokens page hands it out.
 *
 * @param  list<string>  $abilities
 */
function apiToken(Workspace $workspace, array $abilities = ['projects:read', 'projects:write'], ?DateTimeInterface $expiresAt = null): string
{
    return $workspace->createToken('Test', $abilities, $expiresAt)->plainTextToken;
}

/**
 * @return array<string, string>
 */
function bearer(string $token): array
{
    // A test sends several requests through one app: forget who the last one authenticated as.
    app('auth')->forgetGuards();

    return ['Authorization' => "Bearer {$token}"];
}

describe('the API', function () {
    it('lists only the projects of the token\'s workspace', function () {
        $workspace = workspace('pro');
        $other = workspace('pro');
        $mine = Project::factory()->for($workspace)->create(['name' => 'Mine']);
        Project::factory()->for($other)->create(['name' => 'Theirs']);

        getJson('/api/v1/projects', bearer(apiToken($workspace)))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.name', 'Mine')
            ->assertJsonMissing(['workspace_id' => $workspace->getKey()]);
    });

    it('never shows another workspace\'s project, even by its id', function () {
        $theirs = Project::factory()->for(workspace('pro'))->create();

        getJson("/api/v1/projects/{$theirs->id}", bearer(apiToken(workspace('pro'))))->assertNotFound();
    });

    it('shows a project of its own workspace', function () {
        $workspace = workspace('pro');
        $project = Project::factory()->for($workspace)->create();

        getJson("/api/v1/projects/{$project->id}", bearer(apiToken($workspace)))
            ->assertOk()
            ->assertJsonPath('data.id', $project->id);
    });

    it('creates a project in the token\'s workspace', function () {
        $workspace = workspace('pro');

        postJson('/api/v1/projects', ['name' => 'From the API'], bearer(apiToken($workspace)))
            ->assertCreated()
            ->assertJsonPath('data.name', 'From the API')
            ->assertJsonPath('data.status', 'active');

        expect($workspace->projects()->pluck('name')->all())->toBe(['From the API'])
            ->and(Project::count())->toBe(1);
    });

    it('validates what it is given', function () {
        postJson('/api/v1/projects', ['status' => 'nonsense'], bearer(apiToken(workspace('pro'))))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'status']);
    });

    it('stops creating at the plan\'s project limit', function () {
        $workspace = workspace('pro');
        Features::override($workspace, 'projects', 2);
        Project::factory()->count(2)->for($workspace)->create();

        postJson('/api/v1/projects', ['name' => 'One too many'], bearer(apiToken($workspace)))
            ->assertForbidden()
            ->assertJsonPath('limit', 2)
            ->assertJsonStructure(['message', 'upgrade_url']);

        expect($workspace->projects()->count())->toBe(2);
    });

    it('refuses a plan without API access, even with a valid token', function () {
        $workspace = workspace('pro');
        $token = apiToken($workspace);
        $workspace->changePlan('free');

        getJson('/api/v1/projects', bearer($token))
            ->assertForbidden()
            ->assertJsonPath('message', "The API is not included in this workspace's plan.");
    });

    it('needs the ability for each route', function () {
        $workspace = workspace('pro');

        postJson('/api/v1/projects', ['name' => 'Nope'], bearer(apiToken($workspace, ['projects:read'])))->assertForbidden();
        getJson('/api/v1/projects', bearer(apiToken($workspace, ['projects:write'])))->assertForbidden();

        expect($workspace->projects()->count())->toBe(0);
    });

    it('refuses no token, a wrong one, a revoked one and an expired one', function () {
        $workspace = workspace('pro');
        $revoked = apiToken($workspace);
        $workspace->tokens()->delete();

        getJson('/api/v1/projects')->assertUnauthorized();
        getJson('/api/v1/projects', bearer('1|not-a-real-token'))->assertUnauthorized();
        getJson('/api/v1/projects', bearer($revoked))->assertUnauthorized();
        getJson('/api/v1/projects', bearer(apiToken($workspace, expiresAt: now()->subMinute())))->assertUnauthorized();
    });

    it('answers 401 in JSON to a client that does not ask for JSON', function () {
        $this->get('/api/v1/projects')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    });

    it('never answers to a signed-in browser session', function () {
        $workspace = workspace('pro');
        inWorkspace($workspace);

        getJson('/api/v1/projects')->assertUnauthorized();
    });

    it('limits each token to 60 requests a minute', function () {
        $token = apiToken(workspace('pro'));
        $other = apiToken(workspace('pro'));

        foreach (range(1, 60) as $request) {
            getJson('/api/v1/projects', bearer($token))->assertOk();
        }

        getJson('/api/v1/projects', bearer($token))->assertTooManyRequests();
        getJson('/api/v1/projects', bearer($other))->assertOk();

        RateLimiter::clear('api');
    });

    it('remembers when a token was last used', function () {
        $workspace = workspace('pro');

        getJson('/api/v1/projects', bearer(apiToken($workspace)))->assertOk();

        expect($workspace->tokens()->first()->last_used_at)->not->toBeNull();
    });
});

describe('the API tokens page', function () {
    it('is for owners and admins', function (string $role, bool $allowed) {
        $workspace = workspace('pro');
        inWorkspace($workspace, memberOf($workspace, $role));

        $allowed
            ? livewire(ApiTokens::class)->assertOk()
            : livewire(ApiTokens::class)->assertForbidden();
    })->with([
        'owner' => ['owner', true],
        'admin' => ['admin', true],
        'member' => ['member', false],
    ]);

    it('creates a token, shows it once and records who made it', function () {
        $workspace = workspace('pro');
        $user = inWorkspace($workspace);

        $page = livewire(ApiTokens::class)
            ->callAction('create', ['name' => 'Zapier', 'abilities' => ['projects:read'], 'expires_in' => '30'])
            ->assertHasNoActionErrors();

        $plain = $page->get('plainTextToken');
        $token = $workspace->tokens()->sole();

        expect($plain)->toBeString()
            ->and($token)->toBeInstanceOf(ApiToken::class)
            ->and($token->name)->toBe('Zapier')
            ->and($token->abilities)->toBe(['projects:read'])
            ->and($token->expires_at->isSameDay(now()->addDays(30)))->toBeTrue()
            ->and($token->created_by_id)->toBe($user->getKey())
            ->and($token->created_by_name)->toBe($user->name)
            ->and($token->token)->not->toBe($plain)
            ->and(AuditEvent::where('event', AuditEventType::ApiTokenCreated)->sole()->description())->toBe('Created the API token Zapier (projects:read)');

        $page->assertSee($plain)->call('dismissToken')->assertDontSee($plain);

        getJson('/api/v1/projects', bearer($plain))->assertOk();
    });

    it('creates a token that never expires', function () {
        $workspace = workspace('pro');
        inWorkspace($workspace);

        livewire(ApiTokens::class)
            ->callAction('create', ['name' => 'Forever', 'abilities' => ['projects:read'], 'expires_in' => 'never']);

        expect($workspace->tokens()->sole()->expires_at)->toBeNull();
    });

    it('lists only this workspace\'s tokens', function () {
        $workspace = workspace('pro');
        $other = workspace('pro');
        $workspace->createToken('Ours');
        $theirs = $other->createToken('Theirs')->accessToken;
        inWorkspace($workspace);

        livewire(ApiTokens::class)
            ->assertCanSeeTableRecords($workspace->tokens()->get())
            ->assertCanNotSeeTableRecords([$theirs]);
    });

    it('revokes a token, which stops working at once', function () {
        $workspace = workspace('pro');
        $new = $workspace->createToken('Old script', ['projects:read']);
        inWorkspace($workspace);

        livewire(ApiTokens::class)->callAction(TestAction::make('revoke')->table($new->accessToken));

        expect($workspace->tokens()->count())->toBe(0)
            ->and(AuditEvent::where('event', AuditEventType::ApiTokenRevoked)->sole()->description())->toBe('Revoked the API token Old script');

        getJson('/api/v1/projects', bearer($new->plainTextToken))->assertUnauthorized();
    });

    it('cannot revoke another workspace\'s token', function () {
        $workspace = workspace('pro');
        $theirs = workspace('pro')->createToken('Theirs')->accessToken;
        inWorkspace($workspace);

        // Not in this workspace's table, so Filament cannot find it at all.
        expect(fn () => livewire(ApiTokens::class)->callAction(TestAction::make('revoke')->table($theirs)))->toThrow(Exception::class, 'no longer exists')
            ->and(ApiToken::find($theirs->getKey()))->not->toBeNull();
    });

    it('offers an upgrade instead on a plan without API access', function () {
        $workspace = workspace('free');
        inWorkspace($workspace);

        livewire(ApiTokens::class)
            ->assertActionHidden('create')
            ->assertActionVisible('upgrade')
            ->assertSee('API access comes with a paid plan.');

        expect($workspace->tokens()->count())->toBe(0);
    });
});

it('takes a workspace\'s tokens with it when it is deleted', function () {
    $workspace = workspace('pro');
    $other = workspace('pro');
    $token = apiToken($workspace);
    $other->createToken('Stays');

    $workspace->delete();

    expect(ApiToken::count())->toBe(1);

    getJson('/api/v1/projects', bearer($token))->assertUnauthorized();
});

it('stops working once its creator no longer manages the workspace', function (string $how) {
    $workspace = workspace('pro');
    $admin = memberOf($workspace, 'admin');
    $others = $workspace->createToken('Created by someone else', ['projects:read'])->plainTextToken;
    inWorkspace($workspace, $admin);

    $plain = livewire(ApiTokens::class)
        ->callAction('create', ['name' => 'Zapier', 'abilities' => ['projects:read'], 'expires_in' => 'never'])
        ->get('plainTextToken');

    match ($how) {
        'removed' => Teams::removeMember($workspace, $admin),
        'made a member' => Teams::changeRole($workspace, $admin, 'member'),
        'account deleted' => $admin->deleteAccount(),
    };

    expect($workspace->tokens()->pluck('name')->all())->toBe(['Created by someone else'])
        ->and(AuditEvent::where('event', AuditEventType::ApiTokenRevoked)->sole()->description())
        // A deleted account's name is taken out of the log (AuditEvent::forget()).
        ->toBe('Revoked the API token Zapier ('.($how === 'account deleted' ? AuditEvent::DELETED_USER : $admin->name).' no longer manages the workspace)');

    getJson('/api/v1/projects', bearer($plain))->assertUnauthorized();
    getJson('/api/v1/projects', bearer($others))->assertOk();
})->with(['removed', 'made a member', 'account deleted']);

it('keeps working when its creator stays a manager', function () {
    $owner = User::factory()->create();
    $workspace = workspace('pro', $owner);
    $plain = $workspace->createToken('Zapier', ['projects:read']);
    $plain->accessToken->forceFill(['created_by_id' => $owner->getKey()])->save();

    // The former owner stays on as an admin.
    Teams::transferOwnership($workspace, $owner, memberOf($workspace, 'admin'));

    expect($workspace->tokens()->count())->toBe(1);

    getJson('/api/v1/projects', bearer($plain->plainTextToken))->assertOk();
});

it('takes a former manager\'s name out of the revocation entry when they delete their account', function () {
    $workspace = workspace('pro');
    $admin = memberOf($workspace, 'admin');
    $workspace->createToken('Zapier')->accessToken->forceFill(['created_by_id' => $admin->getKey()])->save();

    $admin->deleteAccount();

    expect(AuditEvent::where('event', AuditEventType::ApiTokenRevoked)->sole()->description())
        ->not->toContain($admin->name);
});
