<?php

// The admin panel: the revenue dashboard and the Users, Workspaces and Activity resources.
return [

    'revenue' => [
        'mrr' => 'Monthly recurring revenue',
        'per_year' => ':amount a year',
        'subscriptions' => 'Paying subscriptions',
        'subscriptions_description' => 'Active or retrying a payment',
        'trials' => 'On a free trial',
        'trials_description' => 'Not counted in revenue yet',
        'churned' => 'Churned, last 30 days',
        'no_subscriptions' => 'No subscriptions yet',
        'churn_rate' => ':rate% of subscriptions',
    ],

    'growth' => [
        'heading' => 'Sign-ups and new workspaces',
        'description' => 'Last 30 days: :users sign-ups, :workspaces new workspaces, :changes plan changes.',
        'sign_ups' => 'Sign-ups',
        'new_workspaces' => 'New workspaces',
    ],

    'plan_distribution' => [
        'heading' => 'Workspaces by plan',
        'dataset' => 'Workspaces',
    ],

    'revenue_by_plan' => [
        'heading' => 'Monthly recurring revenue by plan',
        'dataset' => 'MRR (:currency)',
    ],

    'users' => [
        'model' => 'user',
        'plural_model' => 'users',
        'title' => 'User',
        'plural_title' => 'Users',
        'columns' => [
            'name' => 'Name',
            'email' => 'Email',
            'workspaces' => 'Workspaces',
            'admin' => 'Admin',
            'signed_up' => 'Signed up',
        ],
    ],

    'workspaces' => [
        'model' => 'workspace',
        'plural_model' => 'workspaces',
        'title' => 'Workspace',
        'plural_title' => 'Workspaces',
        'columns' => [
            'name' => 'Name',
            'plan' => 'Plan',
            'subscription' => 'Subscription',
            'none' => 'None',
            'members' => 'Members',
            'created' => 'Created',
        ],
        'actions' => [
            'activity' => 'Activity',
            'change_plan' => 'Change plan',
        ],
        'fields' => [
            'plan' => 'Plan',
            'plan_help' => 'A Stripe subscription sets the plan again whenever it changes.',
        ],
        'plan_changed' => 'Plan changed',
    ],

    'activity' => [
        'navigation' => 'Activity',
        'model' => 'activity entry',
        'plural_model' => 'activity entries',
        'title' => 'Activity Entry',
        'plural_title' => 'Activity Entries',
        'columns' => [
            'workspace' => 'Workspace',
            'event' => 'Event',
            'description' => 'Description',
            'by' => 'By',
            'when' => 'When',
        ],
        'filters' => [
            'event' => 'Event',
            'workspace' => 'Workspace',
            'support_logins' => 'During a support login',
        ],
    ],

];
