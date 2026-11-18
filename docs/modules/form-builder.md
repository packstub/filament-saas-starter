# Forms with Form Builder

[Form Builder](https://github.com/packstub/filament-form-builder) lets each workspace design its own forms (contact forms, surveys, sign-up sheets, intake forms) in a **Forms** page, publish them on a hosted page, an iframe or a script embed, and read, export and get e-mailed the submissions. The number of forms is a plan limit.

## What you need

- `packstub/filament-form-builder` 1.4 or later, stable on Packagist, PHP 8.3+.
- A queue worker in production if forms send e-mails or webhooks (`QUEUE_CONNECTION=database` or Redis); the kit's `sync` default is fine while you try it.
- A disk that lasts for uploaded files (`FORM_BUILDER_UPLOADS_DISK`), as for Session Replay: S3 or R2 on Laravel Cloud.

## 1. Install

Publish the config first: the forms table takes its workspace column from it.

```bash run
composer require packstub/filament-form-builder:^1.4
php artisan vendor:publish --tag=packstub-form-builder-config
```

In `config/packstub-form-builder.php`, scope forms to the workspace:

```php in=config/packstub-form-builder.php replace="'tenancy' => [" through="],"
// Every form belongs to the current workspace (Filament's tenant).
'tenancy' => [
    'enabled' => true,
    'column' => 'workspace_id',
    'model' => App\Models\Workspace::class,
    'resolver' => null,
],
```

Then migrate:

```bash run
php artisan vendor:publish --tag=packstub-form-builder-migrations
php artisan migrate
```

Four tables: `form_builder_forms` (with an indexed `workspace_id`), `form_builder_submissions`, `form_builder_share_links` and `form_builder_webhook_deliveries`.

## 2. Register the plugin on the app panel

In `app/Filament/Plugins.php`:

```php use=app/Filament/Plugins.php
use Packstub\FormBuilder\FormBuilderPlugin;
```

```php in=app/Filament/Plugins.php before="function app()|];"
// Forms and their submissions, per workspace.
FormBuilderPlugin::make()->navigationGroup('Content')->navigationBadge(),
```

The **Forms** page lists the current workspace's forms only, and a new form gets the workspace's key. A form's public address is `/forms/{slug}`; slugs are unique across all workspaces, so a workspace may be asked to pick another one.

## 3. A plan limit on forms

In `config/packstub-features.php`, a feature:

```php in=config/packstub-features.php after="'features' => [|'seats' =>"
'forms' => ['label' => 'Forms', 'type' => 'limit', 'default' => 1, 'unit' => 'forms', 'group' => 'Workspace', 'description' => 'Forms in this workspace.'],
```

and a value per plan (`null` is unlimited):

```php in=config/packstub-features.php after="'plans' => [|'free' =>|'seats' =>"
'forms' => 1,
```

```php in=config/packstub-features.php after="'plans' => [|'pro' =>|'seats' =>"
'forms' => 20,
```

```php in=config/packstub-features.php after="'plans' => [|'business' =>|'seats' =>"
'forms' => null,
```

Count them in `app/Support/Plans.php`:

```php use=app/Support/Plans.php
use Packstub\FormBuilder\Models\Form;
```

```php in=app/Support/Plans.php after="->usage('seats',"
->usage('forms', fn (?Workspace $workspace): int => $workspace ? Form::query()->withoutGlobalScopes()->where('workspace_id', $workspace->getKey())->count() : 0)
```

and hold the limit with a policy, `app/Policies/FormPolicy.php`:

```php file=app/Policies/FormPolicy.php
<?php

namespace App\Policies;

use App\Models\User;
use Filament\Facades\Filament;
use Packstub\Features\Facades\Features;

/**
 * Form Builder's forms: a new one only while the plan has room. The rest
 * is the resource's, which only ever shows the current workspace's forms.
 */
class FormPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $ability === 'create' ? null : true;
    }

    public function create(User $user): bool
    {
        return Features::canUse('forms', Filament::getTenant());
    }
}
```

Laravel finds policies for `App\Models` by name only, so register this one in `app/Providers/AppServiceProvider.php`:

```php use=app/Providers/AppServiceProvider.php
use App\Policies\FormPolicy;
use Illuminate\Support\Facades\Gate;
use Packstub\FormBuilder\Models\Form;
```

```php in=app/Providers/AppServiceProvider.php after="function boot()|Model::shouldBeStrict("

// Form Builder's forms count against the plan's `forms` limit.
Gate::policy(Form::class, FormPolicy::class);
```

At the limit, **New form** is gone from the Forms page, as **New project** is on Projects.

## 4. Say where its tables live

The forms carry `workspace_id` but keep integer keys, so they are listed as central. In `centralTables()` in `tests/Feature/TableRulesTest.php`:

```php in=tests/Feature/TableRulesTest.php before="function centralTables()|];"
// Form Builder: forms carry workspace_id but integer keys; public form URLs are looked up before any workspace.
'form_builder_forms' => 'public form URLs are looked up before the workspace is known',
'form_builder_submissions' => 'forms',
'form_builder_share_links' => 'forms',
'form_builder_webhook_deliveries' => 'forms',
```

## 5. Delete forms with their workspace

Create `app/Listeners/DeleteWorkspaceForms.php`. It also forgets who created a form or sent a submission when that person deletes their account:

```php file=app/Listeners/DeleteWorkspaceForms.php
<?php

namespace App\Listeners;

use App\Events\AccountDeleting;
use App\Events\WorkspaceDeleting;
use Illuminate\Support\Facades\Storage;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Models\ShareLink;
use Packstub\FormBuilder\Uploads\Uploads;

/**
 * A deleted workspace's forms go with it: their submissions, share links
 * and webhook deliveries by foreign key, their uploaded files here. A
 * deleted person is no longer named as a form's creator or a submitter.
 */
class DeleteWorkspaceForms
{
    public function handle(WorkspaceDeleting|AccountDeleting $event): void
    {
        if ($event instanceof AccountDeleting) {
            foreach ([Form::class, FormSubmission::class, ShareLink::class] as $model) {
                $model::query()->withoutGlobalScopes()->where('user_id', $event->user->getKey())->update(['user_id' => null]);
            }

            return;
        }

        Form::query()->withoutGlobalScopes()->where('workspace_id', $event->workspace->getKey())
            ->each(function (Form $form): void {
                Storage::disk(Uploads::disk())->deleteDirectory(Uploads::directory($form));
                $form->delete();
            });
    }
}
```

## 6. Test it

`tests/Feature/Modules/FormBuilderTest.php`:

```php file=tests/Feature/Modules/FormBuilderTest.php
<?php

use App\Models\User;
use App\Models\Workspace;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;

use function Pest\Laravel\actingAs;

function formOf(Workspace $workspace, string $name = 'Contact'): Form
{
    return Form::query()->withoutGlobalScopes()->forceCreate([
        'name' => $name,
        'slug' => str($name)->slug().'-'.$workspace->id,
        'workspace_id' => $workspace->id,
    ]);
}

it('shows each workspace its own forms', function () {
    $workspace = workspace();
    formOf($workspace, 'Contact');
    formOf(workspace(), 'Not yours');

    actingAs(memberOf($workspace, 'member'))
        ->get("/app/{$workspace->slug}/forms")
        ->assertOk()
        ->assertSee('Contact')
        ->assertDontSee('Not yours');
});

it('lets a workspace create forms while its plan has room', function (string $plan, int $forms, bool $allowed) {
    $workspace = workspace($plan);
    foreach (range(1, $forms) as $n) {
        formOf($workspace, "Form {$n}");
    }

    $user = inWorkspace($workspace);

    expect($user->can('create', Form::class))->toBe($allowed);
})->with([
    'free, at the limit' => ['free', 1, false],
    'pro, room left' => ['pro', 1, true],
]);

it('puts a new form in the current workspace', function () {
    $workspace = workspace();
    inWorkspace($workspace);

    $form = Form::query()->create(['name' => 'Survey', 'slug' => 'survey']);

    expect((string) $form->workspace_id)->toBe((string) $workspace->id);
});

it('deletes the forms of a deleted workspace, and forgets a deleted person', function () {
    $owner = User::factory()->create();
    $workspace = workspace(owner: $owner);
    formOf($workspace);
    $theirs = formOf(workspace('pro'), 'Theirs');
    $theirs->forceFill(['user_id' => $owner->id])->save();
    FormSubmission::query()->forceCreate(['form_id' => $theirs->id, 'user_id' => $owner->id, 'data' => []]);

    $owner->deleteAccount();

    expect(Form::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$theirs->id])
        ->and($theirs->fresh()->user_id)->toBeNull()
        ->and(FormSubmission::query()->sole()->user_id)->toBeNull();
});
```

```bash run
composer lint
composer test
```

## A database per workspace later

Two ways, pick one when you move:

- **Keep forms central** (simplest): leave the four tables on the central connection. Public form pages and submissions arrive before any workspace is known, and `workspace_id` says whose they are.
- **Move them into each workspace's database**: set `tenancy.enabled` to `false` (each database holds one workspace's forms, as the package's [Extending](https://github.com/packstub/filament-form-builder/blob/main/docs/extending.md) page says), move the migrations into `database/migrations/workspace`, and route `/forms/{slug}` to the right workspace yourself (a subdomain, or the workspace in the URL).

## Removing it

Delete the plugin lines and import from `app/Filament/Plugins.php`, the policy and its `Gate::policy()` line, the `forms` feature, plan values and `usage()` line, `app/Listeners/DeleteWorkspaceForms.php`, `tests/Feature/Modules/FormBuilderTest.php` and the four lines in `centralTables()`, then:

```bash
composer remove packstub/filament-form-builder
rm config/packstub-form-builder.php
```

Drop the four tables with a migration of your own, delete the published `*_form_builder_*` migrations, and the `form-builder` folder on the uploads disk.
