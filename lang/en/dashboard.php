<?php

// The dashboard widgets: the plan overview and the getting-started checklist.
return [

    'overview' => [
        'plan' => 'Plan',
        'projects' => 'Projects',
        'seats' => 'Seats',
        'seats_description' => 'Members and pending invitations',
        'trial_ends' => 'Trial ends :date',
        'of' => ':used of :limit',
    ],

    'getting_started' => [
        'heading' => 'Get started',
        'progress' => ':done of :total done. Each step ticks itself off once it is done.',
        'dismiss' => 'Dismiss',
        'aria_label' => 'Getting started',
        'done' => '(done)',
        'steps' => [
            'invite' => [
                'title' => 'Invite a teammate',
                'description' => 'Work together: invite people by e-mail or share a link.',
                'action' => 'Invite',
            ],
            'project' => [
                'title' => 'Create your first project',
                'description' => 'Everything your team works on starts with a project.',
                'action' => 'Create a project',
            ],
            'plan' => [
                'title' => 'Choose a plan',
                'description' => 'More projects, more seats and the API, when you need them.',
                'action' => 'See plans',
            ],
            'two-factor' => [
                'title' => 'Turn on two-factor authentication',
                'description' => 'Keep your account safe with a code from an authenticator app.',
                'action' => 'Set it up',
            ],
        ],
    ],

];
