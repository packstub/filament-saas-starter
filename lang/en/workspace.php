<?php

// Workspace settings and the "New workspace" page.
return [

    'settings' => 'Workspace settings',
    'new' => 'New workspace',

    'logo' => [
        'label' => 'Logo',
        'help' => 'Shown in the workspace menu and at the top of the e-mails about this workspace. PNG, JPEG or WebP; square works best.',
    ],

    'export' => [
        'heading' => 'Export workspace data',
        'description' => 'The workspace, its members, invitations, subscriptions, overrides and projects, as one JSON file.',
        'action' => 'Download workspace data',
    ],

    'delete' => [
        'heading' => 'Delete workspace',
        'description' => 'Its projects, members, invitations and subscription go with it, for good.',
        'action' => 'Delete workspace',
        'modal_heading' => 'Delete :workspace?',
        'modal_description' => 'Its projects, members, invitations and subscription are removed, and the subscription is cancelled now. This cannot be undone.',
        'confirm_name' => 'Type the name of the workspace to confirm',
        'wrong_name' => 'That is not the name of this workspace.',
        'stripe_failed' => 'The subscription could not be cancelled, so the workspace was kept',
        'try_again' => 'Please try again in a moment.',
        'deleted' => 'Workspace deleted',
    ],

];
