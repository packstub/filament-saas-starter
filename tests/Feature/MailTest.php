<?php

use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AccountDeleted;
use App\Notifications\PaymentFailed;
use App\Notifications\WorkspaceDeleted;
use Filament\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Packstub\Teams\Facades\Teams;
use Packstub\Teams\Mail\TeamInvitationMail;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('public');
    config(['app.name' => 'Projectly', 'site.company' => 'Projectly Ltd', 'site.contact_email' => 'help@projectly.test']);
});

/**
 * A workspace with a logo on the uploads disk.
 */
function workspaceWithLogo(string $plan = 'pro'): Workspace
{
    $workspace = workspace($plan);
    $path = "workspaces/{$workspace->id}/logo/logo.png";
    Storage::disk('public')->put($path, 'png');
    $workspace->update(['logo_path' => $path]);

    return $workspace;
}

it('brands every e-mail with the app, the company and the kit\'s theme', function () {
    $user = User::factory()->unverified()->create();

    $notification = new VerifyEmail;
    $notification->url = 'https://projectly.test/verify';

    $html = (string) $notification->toMail($user)->render();

    expect($html)->toContain('Projectly')
        ->toContain('Projectly Ltd')
        ->toContain('help@projectly.test')
        // The button colour of resources/views/vendor/mail/html/themes/saas.css.
        ->toContain('#4f46e5');
});

it('puts the workspace\'s name and logo on its invitation', function () {
    $workspace = workspaceWithLogo();
    $owner = Teams::ownersOf($workspace)->first();

    $invitation = Teams::invite($workspace, 'nina@example.com', 'member', $owner, send: false);
    $html = (new TeamInvitationMail($invitation))->render();

    expect($html)->toContain($workspace->name)
        ->toContain(Storage::disk('public')->url($workspace->logo_path))
        ->toContain('Sent by')
        ->toContain($invitation->acceptUrl());
});

it('tells the owners, and only them, when a payment fails', function () {
    Notification::fake();
    config(['cashier.webhook.secret' => 'whsec_test']);

    $workspace = workspace('pro');
    $workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    $admin = memberOf($workspace, 'admin');

    $event = stripeSubscriptionEvent('updated', $workspace, 'price_pro', 'past_due');
    $event['data']['previous_attributes'] = ['status' => 'active'];
    postSignedStripeEvent($event, 'whsec_test')->assertOk();

    Notification::assertSentTo(Teams::ownersOf($workspace)->get(), PaymentFailed::class);
    Notification::assertNotSentTo($admin, PaymentFailed::class);
});

it('tells the owners once per failure, not on every retry', function (array $previous) {
    Notification::fake();
    config(['cashier.webhook.secret' => 'whsec_test']);

    $workspace = workspace('pro');
    $workspace->forceFill(['stripe_id' => 'cus_test'])->save();

    $event = stripeSubscriptionEvent('updated', $workspace, 'price_pro', 'past_due');
    $event['data']['previous_attributes'] = $previous;
    postSignedStripeEvent($event, 'whsec_test')->assertOk();

    Notification::assertNothingSent();
})->with([
    'already past due' => [['status' => 'past_due']],
    'another change' => [['cancel_at_period_end' => true]],
]);

it('renders the failed payment notice with the workspace and a link to Billing', function () {
    $workspace = workspaceWithLogo();
    $owner = Teams::ownersOf($workspace)->first();

    $mail = (new PaymentFailed($workspace))->toMail($owner);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe("We could not collect the payment for {$workspace->name}")
        ->and($html)->toContain('Your payment did not go through')
        ->toContain(Storage::disk('public')->url($workspace->logo_path))
        ->toContain("/app/{$workspace->slug}/billing");

    expect((new PaymentFailed($workspace))->toDatabase($owner))->toHaveKey('title', "The payment for {$workspace->name} failed");
});

it('tells every member that a workspace was deleted, and who deleted it', function () {
    Notification::fake();

    $workspace = workspace('pro');
    $owner = Teams::ownersOf($workspace)->first();
    $member = memberOf($workspace, 'member');
    $outsider = User::factory()->create();

    actingAs($owner);
    $workspace->delete();

    foreach ([$owner, $member] as $user) {
        Notification::assertSentOnDemand(WorkspaceDeleted::class, fn (WorkspaceDeleted $notice, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === [$user->email => $user->name]
            && $notice->workspace === $workspace->name
            && $notice->deletedBy === $owner->name);
    }

    Notification::assertSentOnDemandTimes(WorkspaceDeleted::class, 2);
    Notification::assertNotSentTo($outsider, WorkspaceDeleted::class);
});

it('sends no deletion notice when the deletion rolls back', function () {
    Notification::fake();

    $workspace = workspace();
    Workspace::deleted(fn () => throw new RuntimeException('The database went away.'));

    expect(fn () => $workspace->delete())->toThrow(RuntimeException::class);

    Notification::assertNothingSent();
});

it('confirms a deleted account to its address, without a workspace notice of its own', function () {
    Notification::fake();

    $user = User::factory()->create();
    workspace(owner: $user);

    $user->deleteAccount();

    Notification::assertSentOnDemand(AccountDeleted::class, fn (AccountDeleted $notice, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === [$user->email => $user->name]);
    Notification::assertSentOnDemandTimes(WorkspaceDeleted::class, 0);
});

it('renders the deletion notices', function () {
    $workspace = (string) (new WorkspaceDeleted('Acme', 'Olivia Bennett'))->toMail(new AnonymousNotifiable)->render();
    $account = (string) (new AccountDeleted('Olivia Bennett'))->toMail(new AnonymousNotifiable)->render();

    expect($workspace)->toContain('Acme was deleted')->toContain('Olivia Bennett deleted the workspace')
        ->and($account)->toContain('Your account was deleted')->toContain('help@projectly.test');
});
