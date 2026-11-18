# Themes with Nitpick

[Nitpick](https://github.com/packstub/filament-nitpick) gives the customer panel designed themes (Aurora, Midnight) and an **Appearance** page in the user menu where each person picks a theme and an accent colour. Owners and admins also get a **For <workspace>** section there: one theme for everyone in the workspace, locked if they like. The admin panel keeps the kit's look.

## What you need

- `packstub/filament-nitpick` 1.0 or later, stable on Packagist, PHP 8.3+, Filament 5.8.3+ (the kit has a later 5.x).
- Nothing else: no queue, no build step. The themes are plain CSS published by `php artisan filament:assets`, which `composer install` already runs (`post-autoload-dump`).

## 1. Install

```bash run
composer require packstub/filament-nitpick:^1.0
php artisan vendor:publish --tag=nitpick-migrations
php artisan migrate
```

The migrations add two tables: `nitpick_theme_preferences` (each person's choice) and `nitpick_panel_themes` (what owners set for their workspace).

## 2. Register the plugin on the app panel

In `app/Filament/Plugins.php`, import the classes:

```php use=app/Filament/Plugins.php
use Filament\Facades\Filament;
use Packstub\Nitpick\NitpickPlugin;
```

and add the plugin to `app()`:

```php in=app/Filament/Plugins.php before="function app()|];"
NitpickPlugin::make()
    ->default('aurora')
    // Each person picks a theme and an accent; owners and admins set one for the whole workspace.
    ->picker(accent: true, manage: fn (): bool => (bool) auth()->user()?->can('update', Filament::getTenant())),
```

`manage` asks the kit's `WorkspacePolicy::update()`, the same rule as **Workspace settings**: owners and admins. Leave `manage` out to give everyone their own choice only.

### On some plans only

To sell "your own theme for the whole workspace" as a plan feature, add a boolean to `config/packstub-features.php` (`'workspace_theme' => ['label' => 'Workspace theme', 'type' => 'boolean', 'default' => false, 'group' => 'Workspace']`, `true` under the plans that have it) and check it next to the role:

```php
->picker(accent: true, manage: fn (): bool => (bool) auth()->user()?->can('update', Filament::getTenant())
    && Features::enabled('workspace_theme', Filament::getTenant())),
```

## 3. Say where its tables live

Both tables are central: a person's choice follows them across workspaces, and a workspace's setting is keyed by `tenant_type` and `tenant_id` (Nitpick keeps it central under a database per workspace too). Add them to `centralTables()` in `tests/Feature/TableRulesTest.php`:

```php in=tests/Feature/TableRulesTest.php before="function centralTables()|];"
// Nitpick: a person's theme, and each workspace's theme keyed by tenant_type and tenant_id.
'nitpick_theme_preferences' => 'a person\'s theme, across workspaces',
'nitpick_panel_themes' => 'keyed by the workspace, stays central',
```

## 4. Clean up after a deleted workspace or account

Create `app/Listeners/ForgetNitpickThemes.php`. Laravel finds it by the events its `handle()` method takes:

```php file=app/Listeners/ForgetNitpickThemes.php
<?php

namespace App\Listeners;

use App\Events\AccountDeleting;
use App\Events\WorkspaceDeleting;
use Illuminate\Support\Facades\DB;
use Packstub\Nitpick\ThemeManager;

/**
 * Nitpick's rows go with the workspace or the person they are about.
 */
class ForgetNitpickThemes
{
    public function handle(WorkspaceDeleting|AccountDeleting $event): void
    {
        $rows = DB::connection(ThemeManager::connectionName());

        if ($event instanceof WorkspaceDeleting) {
            $rows->table(ThemeManager::panelTableName())
                ->where('tenant_type', $event->workspace->getMorphClass())
                ->where('tenant_id', (string) $event->workspace->getKey())
                ->delete();

            return;
        }

        $rows->table(ThemeManager::tableName())
            ->where('user_type', $event->user->getMorphClass())
            ->where('user_id', $event->user->getKey())
            ->delete();
    }
}
```

## 5. Test it

`tests/Feature/Modules/NitpickTest.php`:

```php file=tests/Feature/Modules/NitpickTest.php
<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Packstub\Nitpick\Filament\Pages\Appearance;
use Packstub\Nitpick\NitpickPlugin;

use function Pest\Laravel\actingAs;

it('lets every member pick a theme on the Appearance page', function (string $role) {
    $workspace = workspace();

    actingAs(memberOf($workspace, $role))
        ->get(Appearance::getUrl(panel: 'app', tenant: $workspace))
        ->assertOk()
        ->assertSee('Midnight');
})->with(['owner', 'admin', 'member']);

it('lets owners and admins set the theme for their workspace, and nobody else', function (string $role, bool $manages) {
    $workspace = workspace();

    inWorkspace($workspace, memberOf($workspace, $role));

    expect(NitpickPlugin::get()->canManage())->toBe($manages);
})->with([
    ['owner', true],
    ['admin', true],
    ['member', false],
]);

it('forgets the theme of a deleted workspace and of a deleted account', function () {
    $owner = User::factory()->create();
    $workspace = workspace(owner: $owner);
    $other = workspace();

    inWorkspace($workspace, $owner);
    NitpickPlugin::get()->setPanelTheme('midnight');
    NitpickPlugin::get()->setUserTheme('midnight');
    inWorkspace($other);
    NitpickPlugin::get()->setPanelTheme('midnight');

    $owner->deleteAccount();

    expect(DB::table('nitpick_panel_themes')->pluck('tenant_id')->all())->toBe([(string) $other->id])
        ->and(DB::table('nitpick_theme_preferences')->where('user_id', $owner->id)->exists())->toBeFalse()
        ->and(rowsLeftOf($workspace))->toBe([]);
});
```

```bash run
composer lint
composer test
```

## A database per workspace later

Nothing moves. Both tables stay on the central connection, which Nitpick follows from the user model; set `connection` in `config/nitpick.php` (`php artisan vendor:publish --tag=nitpick-config`) if the user model ever moves.

## Removing it

Delete the plugin lines and imports from `app/Filament/Plugins.php`, `app/Listeners/ForgetNitpickThemes.php`, `tests/Feature/Modules/NitpickTest.php` and the two lines in `centralTables()`, then:

```bash
composer remove packstub/filament-nitpick
```

Drop the two tables with a migration of your own (`Schema::dropIfExists('nitpick_panel_themes')`, `Schema::dropIfExists('nitpick_theme_preferences')`) and delete the three published `*_nitpick_*` migrations, or keep them if the data may come back.
