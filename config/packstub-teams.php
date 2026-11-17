<?php

use App\Models\Workspace;

return [

    /*
    |--------------------------------------------------------------------------
    | Team model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model that represents a team. In a Filament panel with
    | tenancy enabled this is the tenant model (`->tenant(Team::class)`), and
    | it must use the `Packstub\Teams\Concerns\HasTeamMembers` trait.
    |
    */

    'team_model' => Workspace::class,

    /*
    |--------------------------------------------------------------------------
    | Membership pivot
    |--------------------------------------------------------------------------
    |
    | Where memberships live. Adjust the table and foreign keys to reuse an
    | existing pivot — for packstub/filament-tenancy use table `tenant_user`
    | with `team_foreign_key` => `tenant_id`.
    |
    */

    'membership' => [
        'table' => 'workspace_user',
        'team_foreign_key' => 'workspace_id',
        'user_foreign_key' => 'user_id',
        'role_column' => 'role',

        // Relationship names the plugin calls when your models define them
        // (otherwise the relations are built from the keys above). Filament
        // Tenancy models use `users` / `tenants`.
        'team_relationship' => 'members',
        'user_relationship' => 'joinedTeams',
    ],

    'invitations_table' => 'team_invitations',

    // Database connection for the pivot and invitations tables. Named, not
    // null, on purpose: it is the app's own connection today, and it stays
    // that one if workspaces ever get databases of their own (Filament
    // Tenancy switches the default connection inside a workspace).
    'connection' => env('PACKSTUB_TEAMS_CONNECTION', env('DB_CONNECTION', 'sqlite')),

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    |
    | `provider` picks how roles are listed and enforced:
    |   - null      auto: Spatie when spatie/laravel-permission is installed
    |               with `permission.teams` enabled, otherwise "simple"
    |   - "simple"  the `roles` list below, stored on the pivot only
    |   - "spatie"  Spatie Permission team roles (managed with Filament Shield)
    |   - a class-string implementing Packstub\Teams\Roles\RoleProvider
    |
    | `owner` is the reserved role that may manage the team and cannot be
    | removed while it is the last one. `default` is assigned to new invites.
    | `managers` lists the roles allowed to invite, change roles and remove
    | members (owners always can).
    |
    | Each role is a label, or an array with `label`, `description` and
    | `permissions` (Jetstream style). Owners implicitly hold every
    | permission; `*` grants all. With the Spatie provider, permissions are
    | answered by Spatie / Shield instead.
    |
    */

    'roles' => [
        'provider' => env('PACKSTUB_TEAMS_ROLE_PROVIDER'),
        'owner' => 'owner',
        'default' => 'member',
        'managers' => ['admin'],
        'roles' => [
            'owner' => ['label' => 'Owner', 'permissions' => ['*']],
            'admin' => ['label' => 'Admin', 'description' => 'Manages members, settings and billing.', 'permissions' => ['create', 'read', 'update', 'delete']],
            'member' => ['label' => 'Member', 'description' => 'Works inside the workspace.', 'permissions' => ['create', 'read', 'update']],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    */

    'invitations' => [
        'expire_after_days' => 7,

        // Only the invited e-mail address may accept. Disable to let any
        // signed-in user accept a link they received.
        'require_email_match' => true,

        // Count pending invitations toward the seat limit.
        'count_pending_toward_seats' => true,

        // Minutes to wait before the same invitation can be re-sent.
        'resend_cooldown_minutes' => 5,

        // How many addresses one "Invite" action may submit.
        'max_batch' => 10,

        // Shareable links (no e-mail): allow creating them from the UI.
        'links' => true,

        // Existing users also get an in-app (database) notification.
        'notify_existing_users' => true,

        // On login/registration, accept every pending invitation addressed
        // to the user's e-mail — not just the one whose link they followed.
        'auto_accept_on_login' => false,

        // `teams:prune-invitations` deletes accepted invitations older than
        // this many days (expired ones are always deleted).
        'prune_accepted_after_days' => 30,

        // Queued: with QUEUE_CONNECTION=sync the e-mail still goes out during the
        // request, and with a real queue it goes through the worker.
        'mail' => [
            'queue' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Personal teams
    |--------------------------------------------------------------------------
    |
    | Create a team for every newly registered user (unless they registered
    | through an invitation link). Provide the factory with
    | `TeamsPlugin::make()->personalTeams(fn (Model $user): Model => ...)`.
    |
    */

    'personal_teams' => [
        // The factory that creates the workspace is `personalTeams()` in AppPanelProvider.
        'enabled' => true,
        'name' => ":name's Workspace",
    ],

    /*
    |--------------------------------------------------------------------------
    | Seats
    |--------------------------------------------------------------------------
    |
    | A global default seat limit (null = unlimited). Override per team with
    | `TeamsPlugin::make()->seatLimit(fn (Model $team) => ...)` — typically
    | driven by the team's plan.
    |
    */

    'seat_limit' => env('PACKSTUB_TEAMS_SEAT_LIMIT'),

];
