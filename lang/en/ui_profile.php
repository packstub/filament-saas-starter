<?php

// The Profile page: picture, connected accounts, data download and account deletion.
return [

    'avatar' => 'Profile picture',

    'connected' => [
        'heading' => 'Connected accounts',
        'with_password' => 'Sign in with these as well as your password.',
        'without_password' => 'You sign in with these. Choose a password above to have one more way in.',
        'connect' => 'Connect :provider',
        'disconnect' => 'Disconnect :provider',
        'disconnect_with_email' => 'Disconnect :provider (:email)',
        'disconnect_heading' => 'Disconnect :provider?',
        'disconnect_description' => 'You will no longer be able to sign in with :provider.',
        'only_way' => 'Choose a password first: this is your only way to sign in.',
        'disconnected' => ':provider is disconnected.',
        'fallback_provider' => 'account',
    ],

    'data' => [
        'heading' => 'Your data',
        'description' => 'Your account, your workspaces and roles, your notifications and every support login into your account, as one JSON file.',
        'download' => 'Download your data',
    ],

    'delete' => [
        'heading' => 'Delete account',
        'description' => 'Your account, and every workspace where you are the only member, go for good.',
        'action' => 'Delete account',
        'modal_heading' => 'Delete your account?',
        'blocked' => 'First hand over ownership of these workspaces under Members, or delete them: :workspaces.',
        'modal_description' => 'You leave every workspace you share with others. Workspaces where you are the only member are deleted, and their subscriptions cancelled. This cannot be undone.',
        'confirm_email' => 'Type your e-mail address to confirm',
        'wrong_email' => 'That is not your e-mail address.',
        'password' => 'Your password',
        'hand_over_first' => 'Hand over or delete the workspaces you are the only owner of first',
        'stripe_failed' => 'A subscription could not be cancelled, so your account was kept',
        'try_again' => 'Please try again in a moment.',
    ],

];
