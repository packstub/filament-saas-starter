<?php

// The API tokens page: creating, listing and revoking a workspace's tokens.
return [

    'title' => 'API tokens',

    'subheading' => 'Let your scripts and integrations use the API at :url on behalf of this workspace.',

    'abilities' => [
        'projects:read' => 'Read projects',
        'projects:write' => 'Create projects',
    ],

    'actions' => [
        'create' => 'New API token',
        'create_submit' => 'Create token',
        'upgrade' => 'Upgrade for API access',
        'revoke' => 'Revoke',
        'revoke_heading' => 'Revoke :name?',
        'revoke_description' => 'Anything that uses this token stops working straight away. This cannot be undone.',
    ],

    'fields' => [
        'name' => 'Name',
        'name_help' => 'What uses it: "Zapier", "Nightly import".',
        'abilities' => 'It may',
        'expires' => 'Expires',
    ],

    'expires_in' => [
        '30' => 'In 30 days',
        '90' => 'In 90 days',
        '365' => 'In a year',
        'never' => 'Never',
    ],

    'columns' => [
        'name' => 'Name',
        'abilities' => 'May',
        'created_by' => 'Created by',
        'created_by_empty' => 'Someone who left',
        'last_used' => 'Last used',
        'expires' => 'Expires',
        'never' => 'Never',
        'created' => 'Created',
    ],

    'empty' => [
        'heading' => 'No API tokens yet',
        'with_access' => 'Create one to call the API from a script or another app.',
        'without_access' => 'API access comes with a paid plan.',
    ],

    'reveal' => [
        'heading' => 'Copy your new token now',
        'description' => 'It is shown only this once. Send it as a bearer token: <code>Authorization: Bearer &lt;token&gt;</code>.',
        'aria_label' => 'Your new API token',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'done' => 'Done',
    ],

];
