<?php

// The Billing page: plans, Stripe setup notices, trials, payments and the buttons around them.
return [

    'title' => 'Billing',

    'subheading' => "You're on the :plan plan.",

    'checkout_success' => [
        'title' => 'Thank you!',
        'body' => 'Your plan updates as soon as Stripe confirms the payment.',
    ],

    'plan_active' => "You're on the :plan plan",

    'intervals' => [
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ],

    'interval_label' => 'Billing interval',

    'setup' => [
        'heading' => 'Add your Stripe keys',
        'description' => 'Billing is ready and waiting for a Stripe account. Until then every workspace stays on its current plan.',
        'step_product' => 'In the Stripe dashboard (test mode), create a product for each paid plan, with a monthly recurring price and, if you sell it yearly, a yearly one.',
        'step_keys' => 'Fill in these keys in <code>.env</code>:',
        'step_webhook' => "Forward Stripe's webhooks to this app:",
    ],

    'coming_soon' => [
        'heading' => 'Paid plans are on their way',
        'description' => 'You can keep working on your current plan in the meantime.',
    ],

    'trial' => [
        'heading' => 'Your free trial ends on :date',
        'description' => 'Your card is charged then, and the plan carries on. Cancel any time before under "Manage billing".',
        'badge' => ':days-day free trial',
    ],

    'first_payment' => [
        'heading' => 'Your payment is not finished yet',
        'description' => 'Complete it to start your plan. Until then the workspace stays on its current plan.',
    ],

    'paused' => [
        'heading' => 'Your trial ended without a payment method',
        'description' => 'Add a card under "Manage billing", then resume your subscription. Until then the workspace is on the free plan.',
    ],

    'payment_failed' => [
        'heading' => 'Your last payment did not go through',
        'description' => 'Update your payment method to keep your plan. You can change plans again once the payment is settled.',
    ],

    'plan' => [
        'current' => 'Current plan',
        'per_seat' => 'Per member: :count member today, updated as people join and leave.|Per member: :count members today, updated as people join and leave.',
        'cancel_to_switch' => 'Cancel your subscription under "Manage billing" to move to this plan.',
    ],

    'actions' => [
        'manage' => 'Manage billing',
        'complete_payment' => 'Complete payment',
        'resume' => 'Resume subscription',
        'choose' => 'Choose :plan',
        'switch_to' => 'Switch to :plan',
        'switch_interval' => 'Switch to :interval billing',
        'change_plan_description' => 'Your subscription moves to this plan now, and Stripe prorates the difference.',
    ],

    'stripe_failed' => [
        'title' => 'Stripe could not complete that',
        'try_again' => 'Please try again in a moment.',
    ],

];
