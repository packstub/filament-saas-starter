<?php

use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Filament\App\Pages\Auth\EditProfile;
use App\Filament\App\Pages\Tenancy\EditWorkspace;
use App\Models\Project;
use App\Support\DataExport;
use Filament\Actions\Testing\TestAction;
use Packstub\Features\Facades\Features;
use Packstub\Teams\Facades\Teams;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Livewire\livewire;

/**
 * What a streamed export sends to the browser, read back as JSON.
 */
function exported(StreamedResponse $response): array
{
    ob_start();
    $response->sendContent();

    return json_decode(ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);
}

it('exports what the app holds about a person', function () {
    $workspace = workspace('pro');
    $user = memberOf($workspace, 'admin');
    $other = workspace();
    Teams::addMember($other, $user, 'member');

    $export = exported(DataExport::download(DataExport::forUser($user), 'me.json'));

    expect($export['account'])->toMatchArray(['name' => $user->name, 'email' => $user->email, 'two_factor_authentication' => false])
        ->and($export['account'])->not->toHaveKeys(['password', 'remember_token', 'app_authentication_secret'])
        ->and(collect($export['workspaces'])->pluck('role', 'slug')->all())->toBe([$workspace->slug => 'admin', $other->slug => 'member'])
        ->and($export['support_logins'])->toBe([]);
});

it('lists every support login into the account in its export', function () {
    $customer = Teams::ownersOf(workspace())->first();

    inAdminPanel();
    livewire(ManageUsers::class)->callAction(TestAction::make('impersonate')->table($customer));

    $export = exported(DataExport::download(DataExport::forUser($customer), 'me.json'));

    expect($export['support_logins'])->toHaveCount(1)
        ->and($export['support_logins'][0]['reason'])->toBe('impersonation');
});

it('exports everything a workspace holds, and nothing of another', function () {
    $workspace = workspace('pro');
    $workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    memberOf($workspace, 'member');
    Teams::invite($workspace, 'nina@example.com', send: false);
    Features::override($workspace, 'projects', 100);
    Project::factory()->count(3)->for($workspace)->create();
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $workspace, 'price_pro_yearly'))->assertOk();

    $other = workspace();
    $stranger = Teams::ownersOf($other)->first();
    Project::factory()->for($other)->create(['name' => 'Not theirs']);
    $workspace->createToken('Zapier', ['projects:read']);
    $other->createToken('Not theirs either');

    $export = exported(DataExport::download(DataExport::forWorkspace($workspace->fresh()), 'acme.json'));

    expect($export['workspace'])->toMatchArray(['name' => $workspace->name, 'plan' => 'pro'])
        ->and($export['members'])->toHaveCount(2)
        ->and($export['invitations'][0])->toMatchArray(['email' => 'nina@example.com', 'accepted_at' => null])
        ->and($export['subscriptions'][0])->toMatchArray(['status' => 'active', 'price' => 'price_pro_yearly'])
        ->and($export['feature_overrides'][0])->toMatchArray(['feature' => 'projects'])
        ->and(collect($export['activity'])->pluck('description'))->toContain('Invited nina@example.com as Member')
        ->and(collect($export['activity'])->pluck('description'))->not->toContain("{$stranger->name} joined as Owner")
        ->and($export['api_tokens'])->toHaveCount(1)
        ->and($export['api_tokens'][0])->toMatchArray(['name' => 'Zapier', 'abilities' => ['projects:read']])->not->toHaveKey('token')
        ->and($export['projects'])->toHaveCount(3)
        ->and(collect($export['projects'])->pluck('name'))->not->toContain('Not theirs')
        ->and($export['projects'][0])->toHaveKeys(['id', 'name', 'status'])->not->toHaveKey('workspace_id');
});

it('writes an empty list for a workspace without projects', function () {
    $export = exported(DataExport::download(DataExport::forWorkspace(workspace()), 'empty.json'));

    expect($export['projects'])->toBe([]);
});

it('links Download your data on the Profile page, but not during a support login', function () {
    $workspace = workspace();
    $owner = Teams::ownersOf($workspace)->first();

    inWorkspace($workspace, $owner);

    livewire(EditProfile::class)
        ->assertSee('Download your data')
        ->assertActionHasUrl('downloadData', route('exports.personal'));

    inAdminPanel();
    livewire(ManageUsers::class)->callAction(TestAction::make('impersonate')->table($owner));
    inWorkspace($workspace, $owner);

    livewire(EditProfile::class)
        ->assertDontSee('Download your data')
        ->assertActionHidden('downloadData');

    // The first refusal after a switch falls back to the customer's panel; after that it is a plain 403.
    get(route('exports.personal'))->assertRedirect()->assertHeaderMissing('Content-Disposition');
    get(route('exports.personal'))->assertForbidden();
});

it('streams the personal export as a download to the person signed in', function () {
    $user = Teams::ownersOf(workspace())->first();

    get(route('exports.personal'))->assertRedirect('/app/login');

    $response = actingAs($user)->get(route('exports.personal'))
        ->assertOk()
        ->assertDownload('my-data-'.now()->format('Y-m-d').'.json');

    expect(json_decode($response->streamedContent(), true)['account']['email'])->toBe($user->email);
});

it('links Export workspace data in Workspace settings, for owners only', function () {
    $workspace = workspace();

    inWorkspace($workspace);

    livewire(EditWorkspace::class)
        ->assertActionHasUrl('export', route('exports.workspace', $workspace));

    inWorkspace($workspace, memberOf($workspace, 'admin'));

    livewire(EditWorkspace::class)
        ->assertDontSee('Export workspace data')
        ->assertActionHidden('export');
});

it('streams the workspace export to its owners and to nobody else', function () {
    $workspace = workspace('pro');
    Project::factory()->count(2)->for($workspace)->create();
    $url = route('exports.workspace', $workspace);

    get($url)->assertRedirect('/app/login');

    foreach ([memberOf($workspace, 'admin'), memberOf($workspace, 'member'), Teams::ownersOf(workspace())->first()] as $someoneElse) {
        actingAs($someoneElse)->get($url)->assertForbidden();
    }

    $response = actingAs(Teams::ownersOf($workspace)->first())->get($url)
        ->assertOk()
        ->assertDownload($workspace->slug.'-'.now()->format('Y-m-d').'.json');

    expect(json_decode($response->streamedContent(), true)['projects'])->toHaveCount(2);
});
