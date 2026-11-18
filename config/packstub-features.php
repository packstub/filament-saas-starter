<?php

use App\Models\Workspace;

return [

    /*
    |--------------------------------------------------------------------------
    | Scope model
    |--------------------------------------------------------------------------
    |
    | The model features are resolved for — usually the Filament tenant
    | (team, organization, workspace). When no scope is given, the current
    | Filament tenant is used.
    |
    */

    'scope_model' => Workspace::class,

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Every feature your app gates on. `type` is `boolean` (on/off) or `limit`
    | (a number; null = unlimited). `default` applies when no plan or override
    | says otherwise. Define them here or with FeaturesPlugin::features().
    |
    */

    'features' => [
        'projects' => ['label' => 'Projects', 'type' => 'limit', 'default' => 3, 'unit' => 'projects', 'group' => 'Workspace', 'description' => 'Projects in this workspace.'],
        'seats' => ['label' => 'Seats', 'type' => 'limit', 'default' => 2, 'unit' => 'seats', 'group' => 'Workspace', 'description' => 'Members plus pending invitations.'],
        'api_access' => ['label' => 'API access', 'type' => 'boolean', 'default' => false, 'group' => 'Workspace', 'description' => 'API tokens and the REST API at /api/v1.'],
        'priority_support' => ['label' => 'Priority support', 'type' => 'boolean', 'default' => false, 'group' => 'Support', 'description' => 'Answers within one business day.'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Named bundles of feature values. Which plan a scope is on comes from
    | FeaturesPlugin::planUsing(fn (Model $scope) => ...) — a column, a
    | subscription, anything — falling back to `default_plan`.
    |
    | Here it is the `workspaces.plan` column, which the Stripe webhook keeps
    | in step with the subscription. A paid plan's Stripe prices and the
    | amounts people see (monthly, yearly) live in config/billing.php under
    | the same key; `price` here is the text for a plan that is not sold.
    |
    */

    'plans' => [
        'free' => ['label' => 'Free', 'description' => 'For trying things out.', 'price' => '$0', 'features' => ['projects' => 3, 'seats' => 2]],
        'pro' => ['label' => 'Pro', 'description' => 'For a growing team.', 'features' => ['projects' => 50, 'seats' => 10, 'api_access' => true]],
        'business' => ['label' => 'Business', 'description' => 'No limits, and help when you need it.', 'features' => ['projects' => null, 'seats' => null, 'api_access' => true, 'priority_support' => true]],
    ],

    'default_plan' => env('PACKSTUB_FEATURES_DEFAULT_PLAN', 'free'),

    /*
    |--------------------------------------------------------------------------
    | Overrides
    |--------------------------------------------------------------------------
    |
    | Per-scope overrides (trials, exceptions, beta access) and global kill
    | switches (scope = null) are stored here. Resolution order:
    | scope override → plan → global override → feature default.
    |
    */

    'overrides_table' => 'feature_overrides',

    // Named, not null, on purpose: see the same key in config/packstub-teams.php.
    'connection' => env('PACKSTUB_FEATURES_CONNECTION', env('DB_CONNECTION', 'sqlite')),

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Resolved values are memoised per request. Set a store to also cache
    | overrides across requests (invalidated whenever an override changes).
    |
    */

    'cache' => [
        'store' => env('PACKSTUB_FEATURES_CACHE_STORE'),
        'ttl' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Upgrade
    |--------------------------------------------------------------------------
    |
    | Where locked features send people. A URL, or a closure via
    | FeaturesPlugin::upgradeUrl(). Null hides the call to action.
    |
    */

    'upgrade_url' => env('PACKSTUB_FEATURES_UPGRADE_URL'),

];
