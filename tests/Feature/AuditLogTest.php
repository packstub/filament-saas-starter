<?php

use App\Enums\AuditEventType;
use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Filament\App\Pages\Activity;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;
use Filament\Actions\Testing\TestAction;
use Packstub\Teams\Facades\Teams;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Livewire\livewire;

/**
 * The descriptions of a workspace's entries, oldest first.
 *
 * @return list<string>
 */
function activityOf(Workspace $workspace): array
{
    return AuditEvent::query()->where('workspace_id', $workspace->id)->oldest('id')->get()
        ->map(fn (AuditEvent $event): string => $event->description())
        ->all();
}

beforeEach(function () {
    $this->workspace = workspace('pro');
    $this->owner = Teams::ownersOf($this->workspace)->first();
    actingAs($this->owner);
});

it('records who joined, changed role, left and took over, and who did it', function () {
    $member = User::factory()->create(['name' => 'Ethan Park']);

    Teams::addMember($this->workspace, $member, 'member');
    Teams::changeRole($this->workspace, $member, 'admin');
    Teams::transferOwnership($this->workspace, $this->owner, $member);
    Teams::removeMember($this->workspace, $this->owner);

    expect(activityOf($this->workspace))->toBe([
        'Plan changed from Free to Pro',
        "{$this->owner->name} joined as Owner",
        'Ethan Park joined as Member',
        'Ethan Park changed from Member to Admin',
        "Ownership moved from {$this->owner->name} to Ethan Park",
        "{$this->owner->name} is no longer a member",
    ]);

    expect(AuditEvent::query()->latest('id')->first())
        ->actor_id->toBe($this->owner->id)
        ->actor_name->toBe($this->owner->name)
        ->impersonator_id->toBeNull();
});

it('records invitations, links and their acceptance', function () {
    $invitation = Teams::invite($this->workspace, 'nina@example.com', 'admin', send: false);
    Teams::createInviteLink($this->workspace);

    $nina = User::factory()->create(['email' => 'nina@example.com', 'name' => 'Nina']);
    Teams::accept($invitation, $nina);

    expect(activityOf($this->workspace))->toContain(
        'Invited nina@example.com as Admin',
        'Created an invitation link for Member',
        'Nina joined as Admin',
        'Nina accepted an invitation',
    );
});

it('records plan changes from Stripe as the system, and from the admin panel as the admin', function () {
    $this->workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    auth()->logout();

    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_business'))->assertOk();

    $entry = AuditEvent::query()->where('event', AuditEventType::PlanChanged)->latest('id')->first();

    expect($entry->description())->toBe('Plan changed from Pro to Business')
        ->and($entry->actorLabel())->toBe('System');

    $admin = admin();
    actingAs($admin);
    $this->workspace->fresh()->changePlan('free');
    // The same plan again is not a change.
    $this->workspace->fresh()->changePlan('free');

    expect(AuditEvent::query()->where('event', AuditEventType::PlanChanged)->count())->toBe(3)
        ->and(AuditEvent::query()->latest('id')->first()->actorLabel())->toBe($admin->name);
});

it('keeps the admin behind a support login', function () {
    inAdminPanel($admin = admin());
    livewire(ManageUsers::class)->callAction(TestAction::make('impersonate')->table($this->owner));

    memberOf($this->workspace, 'member');

    expect(AuditEvent::query()->latest('id')->first())
        ->actor_id->toBe($this->owner->id)
        ->impersonator_id->toBe($admin->id)
        ->and(AuditEvent::query()->latest('id')->first()->actorLabel())->toBe("{$this->owner->name} (support: {$admin->name})");
});

it('replaces the log of a deleted workspace with one entry, and leaves other workspaces alone', function () {
    memberOf($this->workspace, 'member');
    $other = workspace('pro');
    $before = AuditEvent::query()->where('workspace_id', $other->id)->count();

    $this->workspace->delete();

    expect(AuditEvent::query()->where('workspace_id', $this->workspace->id)->get())
        ->toHaveCount(1)
        ->first()->event->toBe(AuditEventType::WorkspaceDeleted)
        ->and(AuditEvent::query()->where('workspace_id', $this->workspace->id)->first()->description())->toBe("Deleted the workspace {$this->workspace->name}")
        ->and(AuditEvent::query()->where('workspace_id', $other->id)->count())->toBe($before);
});

it('keeps entries readable after the people in them are gone', function () {
    $member = memberOf($this->workspace, 'member');
    $member->deleteAccount();

    expect(activityOf($this->workspace))->toContain("{$member->name} joined as Member", "{$member->name} is no longer a member");
});

it('prunes entries older than the retention period', function () {
    $old = AuditEvent::record($this->workspace, AuditEventType::PlanChanged, ['from' => 'Free', 'to' => 'Pro']);
    $old->forceFill(['created_at' => now()->subDays(400)])->save();

    artisan('model:prune', ['--model' => [AuditEvent::class]])->assertSuccessful();

    expect(AuditEvent::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->count())->toBeGreaterThan(0);
});

it('opens Activity for owners and admins only', function (string $role, int $status) {
    actingAs(memberOf($this->workspace, $role))
        ->get("/app/{$this->workspace->slug}/activity")
        ->assertStatus($status);
})->with([
    'owner' => ['owner', 200],
    'admin' => ['admin', 200],
    'member' => ['member', 403],
]);

it('lists the entries of the current workspace only, newest first', function () {
    $other = workspace('business');
    memberOf($this->workspace, 'member');

    inWorkspace($this->workspace, $this->owner);

    $ours = AuditEvent::query()->where('workspace_id', $this->workspace->id)->get();
    $theirs = AuditEvent::query()->where('workspace_id', $other->id)->get();

    livewire(Activity::class)
        ->assertCanSeeTableRecords($ours)
        ->assertCanNotSeeTableRecords($theirs)
        ->assertSee('Plan changed from Free to Pro')
        ->filterTable('event', AuditEventType::MemberAdded->value)
        ->assertCanSeeTableRecords($ours->where('event', AuditEventType::MemberAdded))
        ->assertCanNotSeeTableRecords($ours->where('event', AuditEventType::PlanChanged));

    get("/app/{$other->slug}/activity")->assertNotFound();
});
