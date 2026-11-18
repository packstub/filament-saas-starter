<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Every table is either central (accounts, memberships, billing, the
 * framework's and the packages' own tables) or a workspace's own. A
 * workspace's own table follows database/migrations/workspace/README.md, so
 * it can move to a database per workspace later: its migration in that
 * folder, a ULID key, an indexed `workspace_id`, and no foreign key to a
 * central table. A central table never points at a workspace's own one.
 *
 * A package or module whose tables stay central lists them here with the
 * reason (docs/modules says which, per module).
 */
function centralTables(): array
{
    return [
        // Laravel: accounts, sessions, the cache and the queue.
        'users' => 'accounts',
        'password_reset_tokens' => 'accounts',
        'sessions' => 'accounts',
        'cache' => 'framework',
        'cache_locks' => 'framework',
        'jobs' => 'framework',
        'job_batches' => 'framework',
        'failed_jobs' => 'framework',
        'notifications' => 'a person\'s bell, across workspaces',
        // The tenant and who belongs to it (Filament Teams).
        'workspaces' => 'the tenant itself',
        'workspace_user' => 'memberships',
        'team_invitations' => 'invitations are read before the workspace is known',
        // Plans and billing (Filament Features, Laravel Cashier).
        'feature_overrides' => 'plans',
        'subscriptions' => 'billing',
        'subscription_items' => 'billing',
        // Support logins (Filament Account Switcher).
        'linked_accounts' => 'accounts',
        'account_switches' => 'the support log',
        // The kit's own central tables.
        'audit_events' => 'kept after a workspace is deleted, for the back office',
        'personal_access_tokens' => 'API tokens are looked up before the workspace is known',
        'social_accounts' => 'accounts',
    ];
}

/**
 * The tables the migrations in database/migrations/workspace create.
 *
 * @return list<string>
 */
function workspaceMigrationTables(): array
{
    return collect(File::files(database_path('migrations/workspace')))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->flatMap(fn (SplFileInfo $file): array => Str::matchAll("/Schema::create\\(\\s*'([^']+)'/", File::get($file->getPathname()))->all())
        ->values()
        ->all();
}

it('keeps every table that is not central movable to a database per workspace', function () {
    $central = array_keys(centralTables());
    $fromFolder = workspaceMigrationTables();
    $problems = [];

    $tables = collect(Schema::getTables())->pluck('name')->reject(fn (string $table): bool => $table === 'migrations');

    foreach ($tables->diff($central) as $table) {
        if (! in_array($table, $fromFolder, true)) {
            $problems[] = "{$table}: not central, so its migration belongs in database/migrations/workspace (or list it in centralTables() with the reason)";

            continue;
        }

        $columns = collect(Schema::getColumns($table))->keyBy('name');
        $indexes = collect(Schema::getIndexes($table));

        $primary = $indexes->firstWhere('primary', true);
        if (($primary['columns'] ?? null) !== ['id'] || ! in_array(Str::lower($columns['id']['type_name'] ?? ''), ['char', 'varchar', 'bpchar'], true)) {
            $problems[] = "{$table}: needs a ULID primary key (\$table->ulid('id')->primary())";
        }

        if (! $columns->has('workspace_id')) {
            $problems[] = "{$table}: needs a workspace_id column";
        } elseif (! $indexes->contains(fn (array $index): bool => ($index['columns'][0] ?? null) === 'workspace_id')) {
            $problems[] = "{$table}: needs an index on workspace_id";
        }

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (in_array($foreignKey['foreign_table'], $central, true)) {
                $problems[] = "{$table}: has a foreign key to the central table {$foreignKey['foreign_table']}";
            }
        }
    }

    foreach ($tables->intersect($central) as $table) {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (! in_array($foreignKey['foreign_table'], $central, true)) {
                $problems[] = "{$table}: a central table with a foreign key to the workspace's own {$foreignKey['foreign_table']}";
            }
        }
    }

    expect($problems)->toBe([]);
});

it('never lists a table of database/migrations/workspace as central', function () {
    expect(array_intersect(workspaceMigrationTables(), array_keys(centralTables())))->toBe([])
        ->and(workspaceMigrationTables())->toContain('projects');
});
