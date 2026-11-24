<?php

// The public site: the layout, the home page, and the placeholder Terms and Privacy pages.
// The tagline, description and company come from config/site.php, not from here.
return [

    'layout' => [
        'skip' => 'Skip to content',
        'main_nav' => 'Main',
        'features' => 'Features',
        'pricing' => 'Pricing',
        'faq' => 'FAQ',
        'open_app' => 'Open the app',
        'log_in' => 'Log in',
        'sign_up' => 'Sign up',
        'copyright' => '© :year :company. All rights reserved.',
        'footer_nav' => 'Footer',
        'terms' => 'Terms',
        'privacy' => 'Privacy',
        'contact' => 'Contact',
    ],

    'home' => [
        'trial_badge' => ':days-day free trial on every paid plan',
        'create_workspace' => 'Create your workspace',
        'see_pricing' => 'See pricing',

        // The picture of the app in the hero, drawn in HTML.
        'mock' => [
            'plan' => 'Pro plan',
            'projects' => 'Projects',
            'seats' => 'Seats',
            'this_week' => 'This week',
            'of' => ':used of :total',
            'status' => [
                'active' => 'Active',
                'on_hold' => 'On hold',
                'archived' => 'Archived',
            ],
            'project_names' => [
                'website' => 'Website redesign',
                'mobile' => 'Mobile app',
                'campaign' => 'Spring campaign',
                'move' => 'Office move',
            ],
        ],

        'features' => [
            'heading' => 'Everything your team needs to get going',
            'intro' => 'Sign up, invite your people and get to work in minutes.',
            'items' => [
                'workspace' => [
                    'title' => 'A workspace for every team',
                    'body' => 'Everything lives in a workspace: your projects, your people, your plan. Belong to as many as you like and switch in one click.',
                ],
                'team' => [
                    'title' => 'Bring your team along',
                    'body' => 'Invite people by e-mail or with a link. Owners, admins and members each see and do what their role allows.',
                ],
                'plans' => [
                    'title' => 'Plans that grow with you',
                    'body' => 'Start on the free plan and move up when you need more room. Your usage is always one click away.',
                ],
                'security' => [
                    'title' => 'Secure by default',
                    'body' => 'Verified e-mail addresses, strong passwords and two-factor sign-in with any authenticator app.',
                ],
                'activity' => [
                    'title' => 'Know who did what',
                    'body' => 'An activity log shows who joined, who left, who changed roles and when the plan changed.',
                ],
                'data' => [
                    'title' => 'Your data stays yours',
                    'body' => 'Download everything as one file whenever you like, and close your account or workspace yourself.',
                ],
            ],
        ],

        'pricing' => [
            'heading' => 'Simple pricing for every team',
            'intro' => 'Start for free and upgrade when you need more.',
            'trial' => 'Paid plans start with a :days-day free trial.',
            'interval' => 'Billing interval',
            'intervals' => [
                'monthly' => 'Monthly',
                'yearly' => 'Yearly',
            ],
            'most_popular' => 'Most popular',
            'contact_us' => 'Contact us',
            'per_seat' => 'per seat',
            'cta_free' => 'Start for free',
            'cta_trial' => 'Start your free trial',
            'cta' => 'Get started',
            'cta_plan' => 'on the :plan plan',
            'note' => 'You choose a paid plan from the Billing page of your workspace, after you sign up.',
        ],

        'faq' => [
            'heading' => 'Questions and answers',
            'items' => [
                'free' => [
                    'q' => 'Can I try it for free?',
                    'a' => 'Yes. Every new workspace starts on the free plan, with no card needed.',
                    'a_trial' => 'Yes. Every new workspace starts on the free plan, with no card needed. Paid plans come with a :days-day free trial, so you can try everything before your first payment.',
                ],
                'billed' => [
                    'q' => 'Who is billed: me or my team?',
                    'a' => 'The workspace is. One subscription covers everyone in it, and owners and admins manage it from the Billing page.',
                ],
                'change' => [
                    'q' => 'Can I change plans later?',
                    'a' => 'Any time. Moving up gives you the new limits straight away; moving down keeps everything you already have.',
                ],
                'cancel' => [
                    'q' => 'How do I cancel?',
                    'a' => 'From the Billing page, in a couple of clicks. Your workspace moves to the free plan at the end of the period you paid for.',
                ],
                'data' => [
                    'q' => 'How is my data protected?',
                    'a' => 'Each workspace only ever sees its own data. You can turn on two-factor sign-in, and download or delete your data whenever you like.',
                ],
            ],
            // `:email` is a mailto link made by the view.
            'contact' => 'Something else? Write to us at :email.',
        ],

        'cta' => [
            'heading' => 'Ready when you are',
            'body' => 'Create your workspace in under a minute and invite your team.',
            'button' => 'Get started for free',
        ],
    ],

    'legal' => [
        'placeholder_title' => 'Replace with your own.',
        'placeholder_body' => 'This page is a placeholder that came with the starter kit, not legal advice. Write it with someone who knows the law where you and your customers are.',
        'updated' => 'Last updated :date',
        'date' => '[date]',
        'description' => ':title of :app.',
        // `:email` is a mailto link made by the view.
        'contact_terms' => 'Questions about these terms: :email.',
        'contact_privacy' => 'Questions about your data: :email.',
        'contact' => 'Contact',
    ],

    'terms' => [
        'title' => 'Terms of Service',
        'intro' => 'These terms apply when you use :app (the "service"), provided by :company. By creating an account you agree to them.',
        'account' => [
            'heading' => 'Your account',
            'body' => 'You are responsible for keeping your sign-in details safe and for what happens under your account. Tell us straight away if you think someone else has used it.',
        ],
        'workspaces' => [
            'heading' => 'Workspaces and your team',
            'body' => 'A workspace belongs to its owners. Owners and admins decide who joins it and what each member may do, and they are responsible for the people they invite.',
        ],
        'plans' => [
            'heading' => 'Plans and payment',
            'billing' => 'Paid plans are billed per workspace, in advance, monthly or yearly. Payments are handled by our payment provider.',
            'placeholder' => '[Describe free trials, renewals, refunds, taxes and price changes.]',
            'cancel' => 'You can cancel at any time from the Billing page; the plan runs until the end of the period you paid for.',
        ],
        'content' => [
            'heading' => 'Your content',
            'body' => 'What you put into the service stays yours. You give us permission to store and process it only to provide the service to you. You can download it or delete it at any time.',
        ],
        'use' => [
            'heading' => 'Acceptable use',
            'body' => '[Describe what customers may not do with the service.]',
        ],
        'availability' => [
            'heading' => 'Availability and changes',
            'body' => '[Describe the service level you offer, how you announce changes to the service and to these terms, and how either side can end the agreement.]',
        ],
        'liability' => [
            'heading' => 'Liability',
            'body' => '[Your limitation of liability and governing law.]',
        ],
    ],

    'privacy' => [
        'title' => 'Privacy Policy',
        'intro' => 'This policy explains what personal data :company collects when you use :app, why, and what you can do about it.',
        'collect' => [
            'heading' => 'What we collect',
            'account' => '<strong>Your account:</strong> your name, e-mail address and a hashed password; the two-factor secret if you turn it on.',
            'workspaces' => '<strong>Your workspaces:</strong> what you and your team add, who is a member and with which role, and invitations.',
            'activity' => '<strong>Activity:</strong> a log of membership, role and plan changes in each workspace, kept for [365 days].',
            'billing' => '<strong>Billing:</strong> your plan and subscription. Card details go to our payment provider, Stripe, and never reach our servers.',
            'technical' => '<strong>Technical data:</strong> a session cookie that keeps you signed in, and server logs.',
        ],
        'why' => [
            'heading' => 'Why we use it',
            'body' => 'To provide the service, to bill for it, to keep it secure and to answer you when you write to us. [Add any other purpose, such as product e-mails or analytics, and its legal basis.]',
        ],
        'who' => [
            'heading' => 'Who else sees it',
            'body' => 'The service providers that help us run the service: [hosting], Stripe for payments, [e-mail delivery]. [List each, with where it stores data.]',
        ],
        'rights' => [
            'heading' => 'Your rights',
            'download' => 'Download everything we hold about you from your Profile page, as one file.',
            'delete' => 'Delete your account from your Profile page; workspace owners can export or delete a workspace from its settings.',
            'placeholder' => '[Describe how to ask for a correction, object to a use, or complain to a supervisory authority.]',
        ],
        'cookies' => [
            'heading' => 'Cookies',
            'body' => 'We use only the cookies the service needs to work: one for your session and one that protects forms. [List any others.]',
        ],
    ],

];
