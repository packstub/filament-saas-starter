# Session Replay

[Session Replay](https://github.com/packstub/filament-session-replay) records what your customers do in the app panel (clicks, page changes, errors, slow pages) and lets your team watch it from `/admin`, next to the support logins. A customer writes "the Save button does nothing": open their last session, see what they saw, then sign in as them if you still need to. Recordings made during a support login name the admin who made them.

Customers never see the recordings: the app panel records, only the admin panel lists and plays them.

## What you need

- `packstub/filament-session-replay` 1.1 or later (it brings `packstub/session-replay`), stable on Packagist, PHP 8.3+.
- A disk for the recordings that lasts and that every server sees: the `local` disk is fine on one server, use S3 or R2 on Laravel Cloud, Vapor or containers (`SESSION_REPLAY_DISK`).
- The scheduler running, for the daily clean-up.
- A line in your privacy policy (below).

## 1. Install

```bash run
composer require packstub/filament-session-replay:^1.1
php artisan vendor:publish --tag=session-replay-config
php artisan migrate
```

Skip `php artisan session-replay:install`: it registers a provider of its own after the panel providers, and `AppPanelProvider` has to stay last in `bootstrap/providers.php`. The steps below do the same by hand.

The migrations run from the package and add six tables (`replay_sessions`, `replay_chunks`, `replay_markers`, `replay_assets`, `replay_session_assets`, `replay_asset_owners`); the recordings themselves are gzip files on the disk.

## 2. Record the app panel, watch from the admin panel

In `app/Filament/Plugins.php`:

```php use=app/Filament/Plugins.php
use Packstub\SessionReplay\SessionReplayPlugin;
```

The app panel records and lists nothing:

```php in=app/Filament/Plugins.php before="function app()|];"
// Records every page of the workspace; the recordings are watched from /admin only.
SessionReplayPlugin::make()->resource(false),
```

The admin panel lists every workspace's recordings and is not recorded itself:

```php in=app/Filament/Plugins.php before="function admin()|];"
// Session replays of every workspace, next to Users and Workspaces.
SessionReplayPlugin::make()->record(false)->navigationGroup('Customers'),
```

## 3. Say who may watch

Only admins, and only with two-factor authentication, as `/admin` asks (except locally, where the developer logins skip it). The player's data routes sit outside the admin panel, so the gate says it itself. In `app/Providers/AppServiceProvider.php`:

```php use=app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;
```

and in `boot()`:

```php in=app/Providers/AppServiceProvider.php after="function boot()|Model::shouldBeStrict("

// Session replays are for your own team, never for customers: admins with two-factor
// authentication, as /admin requires, on the player's data routes outside the panel too.
Gate::define('viewSessionReplay', fn (User $user): bool => $user->is_admin
    && (filled($user->getAppAuthenticationSecret()) || app()->isLocal()));
```

Then turn off the package's own list and player pages at `/session-replay`, so recordings are watched inside `/admin`. The data routes behind the player stay, behind the gate above. In `config/session-replay.php`, under `viewer`:

```php in=config/session-replay.php replace="'viewer' => [|'enabled' =>"
// Watched in the admin panel only; the player's data routes stay.
'enabled' => false,
```

## 4. Keep secrets out of recordings

The recorder masks every input and password before anything leaves the browser. Two pages of the kit *show* secrets as text: the **API tokens** page shows a new token once, and the **Profile** page shows the two-factor setup key and recovery codes. Leave both out. In `config/session-replay.php`, under `except_routes`:

```php in=config/session-replay.php after="'except_routes' => ["
// The kit's pages that show a secret as text.
'filament.app.auth.profile',
'filament.app.pages.api-tokens',
```

Everything else a page *shows* is recorded as text: members' names and e-mail addresses, project names, the Billing page. Decide what your team should see:

- mask a field, column or section where you define it: `TextColumn::make('email')->maskInReplay()`, `Section::make('Billing')->blockInReplay()` (see [Masking](https://github.com/packstub/filament-session-replay/blob/main/docs/masking.md));
- or record layout only, every text masked: `'mask_all_text' => true` under `privacy` in `config/session-replay.php`.

The two excluded pages are left out on a full page load. If you later turn on `->spa()` on the app panel, a recording carries on across `wire:navigate` with those pages as a gap: check the package's notes before you do.

## 5. Prune old recordings

In `routes/console.php`:

```php append=routes/console.php
// Session replays older than SESSION_REPLAY_RETENTION_DAYS (30 by default), except pinned ones.
Schedule::command('session-replay:prune')->daily();
```

## 6. Say where its tables live

The recordings are central: your team watches every workspace in one list, and the upload endpoint needs no workspace. Add the tables to `centralTables()` in `tests/Feature/TableRulesTest.php`:

```php in=tests/Feature/TableRulesTest.php before="function centralTables()|];"
// Session Replay: recordings carry their workspace (tenant_type, tenant_id) and are watched from /admin.
'replay_sessions' => 'recordings, watched across workspaces from /admin',
'replay_chunks' => 'recordings',
'replay_markers' => 'recordings',
'replay_assets' => 'stylesheets shared by recordings',
'replay_session_assets' => 'recordings',
'replay_asset_owners' => 'recordings',
```

## 7. Delete recordings with their workspace or their person

Create `app/Listeners/DeleteSessionReplays.php`:

```php file=app/Listeners/DeleteSessionReplays.php
<?php

namespace App\Listeners;

use App\Events\AccountDeleting;
use App\Events\WorkspaceDeleting;
use Illuminate\Support\Facades\DB;
use Packstub\SessionReplay\Models\ReplaySession;

/**
 * A deleted workspace's recordings go with it, and so do a deleted
 * person's, in every workspace they used: once the deletion is committed,
 * since deleting a recording also deletes its files on the disk, which a
 * rolled-back deletion could not bring back.
 */
class DeleteSessionReplays
{
    public function handle(WorkspaceDeleting|AccountDeleting $event): void
    {
        $ids = ($event instanceof WorkspaceDeleting
            ? ReplaySession::query()->forTenant($event->workspace)
            : ReplaySession::query()->forUser($event->user))->pluck('id');

        DB::afterCommit(fn () => ReplaySession::query()->whereKey($ids)
            ->each(fn (ReplaySession $recording) => $recording->delete()));
    }
}
```

## 8. Tell your customers, and ask first where the law says so

- **Consent.** Recording starts without asking (`SESSION_REPLAY_CONSENT=always`). For customers in the EU and the UK, recording what people do in your app usually needs their consent first (ePrivacy, GDPR): set `SESSION_REPLAY_CONSENT=opt-in` and call `SessionReplay.consent(true)` from your consent banner. Ask your own lawyer; this is not legal advice.
- **Your privacy policy** (`resources/views/site/privacy.blade.php`) says what is recorded, who watches and for how long. The package's [Privacy](https://github.com/packstub/session-replay/blob/main/docs/privacy.md#wording-for-your-privacy-policy) page has wording to start from.
- **Retention.** Recordings are deleted after `SESSION_REPLAY_RETENTION_DAYS` (30) by the daily prune, at once when their workspace or their person is deleted, and a person's data export lists them (step 9). Someone who leaves a workspace keeps their recordings in it until they expire.

## 9. List them in a person's data export

A recording is personal data about the person recorded. In `app/Support/DataExport.php`:

```php use=app/Support/DataExport.php
use Packstub\SessionReplay\Models\ReplaySession;
```

at the end of the array `forUser()` returns (when and where, not the recording itself, which is your team's support record):

```php in=app/Support/DataExport.php before="function forUser(|        ];" indent=12
// Session Replay: when this person was recorded, and on which pages.
'session_recordings' => ReplaySession::query()->forUser($user)->oldest('started_at')->get()
    ->map(fn (ReplaySession $recording): array => [
        'started_at' => self::date($recording->started_at),
        'first_page' => $recording->entry_url,
        'pages' => $recording->page_count,
    ])->all(),
```

## 10. Test it

`tests/Feature/Modules/SessionReplayTest.php`:

```php file=tests/Feature/Modules/SessionReplayTest.php
<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Packstub\SessionReplay\Filament\Resources\ReplaySessions\ReplaySessionResource;
use Packstub\SessionReplay\Models\ReplaySession;
use Packstub\Teams\Facades\Teams;

use function Pest\Laravel\actingAs;

function recordingOf(User $user, Workspace $workspace): ReplaySession
{
    return ReplaySession::forceCreate([
        'id' => (string) Str::uuid(),
        'user_type' => $user->getMorphClass(),
        'user_id' => (string) $user->id,
        'tenant_type' => $workspace->getMorphClass(),
        'tenant_id' => (string) $workspace->id,
        'started_at' => now(),
        'last_activity_at' => now(),
    ]);
}

it('records the pages of a workspace', function () {
    $workspace = workspace();

    actingAs(memberOf($workspace, 'member'))
        ->get("/app/{$workspace->slug}/projects")
        ->assertOk()
        ->assertSee('window.__sessionReplay', escape: false);
});

it('never records the pages that show a secret', function (string $page) {
    $workspace = workspace('pro');

    actingAs(Teams::ownersOf($workspace)->first())
        ->get(str_replace('{slug}', $workspace->slug, $page))
        ->assertOk()
        ->assertDontSee('window.__sessionReplay', escape: false);
})->with(['/app/profile', '/app/{slug}/api-tokens']);

it('lets admins watch from the admin panel, and nobody else', function () {
    $workspace = workspace();
    $member = memberOf($workspace, 'member');
    recordingOf($member, $workspace);

    expect(Gate::forUser($member)->allows('viewSessionReplay'))->toBeFalse()
        // An admin who has not set up two-factor authentication yet, outside local.
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('viewSessionReplay'))->toBe(app()->isLocal());

    actingAs(admin())
        ->get(ReplaySessionResource::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee($workspace->name);

    actingAs($member)->get('/session-replay')->assertNotFound();
});

it('deletes the recordings of a deleted workspace and of a deleted account', function () {
    Storage::fake(config('session-replay.storage.disk'));
    $owner = User::factory()->create();
    $workspace = workspace(owner: $owner);
    $other = workspace('pro');
    $teammate = memberOf($other, 'member');
    Teams::addMember($other, $owner, 'member');

    recordingOf($owner, $workspace);
    recordingOf($owner, $other);
    $kept = recordingOf($teammate, $other);

    $owner->deleteAccount();

    expect(ReplaySession::query()->pluck('id')->all())->toBe([$kept->id])
        ->and(rowsLeftOf($workspace))->toBe([]);
});

it('lists a person\'s recordings in their data export', function () {
    $workspace = workspace();
    $member = memberOf($workspace, 'member');
    recordingOf($member, $workspace);

    expect(App\Support\DataExport::forUser($member)['session_recordings'])->toHaveCount(1);
});

it('prunes old recordings every day', function () {
    expect(collect(Schedule::events())->contains(fn ($event): bool => str_contains($event->command, 'session-replay:prune')))->toBeTrue();
});
```

```bash run
composer lint
composer test
```

## A database per workspace later

Nothing moves. Keep the recordings on the central database and say so, so they never follow a workspace's connection: `SESSION_REPLAY_DB_CONNECTION=central` (the name of your central connection) in `.env`. The disk is already shared. See the package's [Tenancy](https://github.com/packstub/filament-session-replay/blob/main/docs/tenancy.md#database-per-tenant-apps) page.

## Removing it

Delete the plugin lines and the import from `app/Filament/Plugins.php`, the gate from `AppServiceProvider`, the schedule line, `app/Listeners/DeleteSessionReplays.php`, `tests/Feature/Modules/SessionReplayTest.php` and the six lines in `centralTables()`, then:

```bash
composer remove packstub/filament-session-replay packstub/session-replay
rm config/session-replay.php
```

The six tables stay until you drop them with a migration of your own; the files are in the disk's `session-replay` folder.
