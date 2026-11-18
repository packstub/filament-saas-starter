<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plans sold through Stripe
    |--------------------------------------------------------------------------
    |
    | The paid plans, keyed by the plan's key in config/packstub-features.php.
    | Each is sold monthly, yearly or both: `price` is the ID of a recurring
    | Stripe price (price_...), `amount` the text people see on the Billing
    | page. Leave a yearly price empty and the plan is sold monthly only; the
    | Monthly / Yearly switch shows once any plan has a yearly price. A plan
    | missing here (such as `free`) is never sold through Stripe.
    |
    | Checkout subscribes a workspace to one of these prices; the webhook
    | reads the subscription's price back into a plan key and saves it on
    | the workspace, which is what the limits are resolved from. Every price
    | here maps back, so a yearly subscriber gets the same plan as a monthly
    | one. A subscription on a price that is not listed keeps the workspace on
    | its current plan and writes a warning to the log.
    |
    | `per_seat` bills a plan per member: the subscription's quantity follows
    | the number of members, updated whenever someone joins or leaves. Its
    | Stripe prices are then per unit ("$9 per seat"), its `amount` should say
    | so, and its `seats` limit in config/packstub-features.php is usually
    | null (unlimited) or a cap. Off for every plan by default: a flat price.
    |
    */

    'plans' => [
        'pro' => [
            // STRIPE_PRICE_PRO is the key's name before monthly and yearly prices, still read.
            'monthly' => ['price' => env('STRIPE_PRICE_PRO_MONTHLY', env('STRIPE_PRICE_PRO')), 'amount' => '$29/mo'],
            'yearly' => ['price' => env('STRIPE_PRICE_PRO_YEARLY'), 'amount' => '$290/yr'],
            'per_seat' => false,
        ],
        'business' => [
            'monthly' => ['price' => env('STRIPE_PRICE_BUSINESS_MONTHLY', env('STRIPE_PRICE_BUSINESS')), 'amount' => '$99/mo'],
            'yearly' => ['price' => env('STRIPE_PRICE_BUSINESS_YEARLY'), 'amount' => '$990/yr'],
            'per_seat' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Free trial
    |--------------------------------------------------------------------------
    |
    | Days a workspace's first subscription runs before the first charge.
    | Stripe Checkout still asks for a card, so the subscription carries on
    | by itself when the trial ends. A workspace that has had a subscription
    | before starts paying straight away. 0 turns trials off.
    |
    */

    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

];
