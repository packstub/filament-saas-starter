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
    */

    'plans' => [
        'pro' => [
            'monthly' => ['price' => env('STRIPE_PRICE_PRO_MONTHLY'), 'amount' => '$29/mo'],
            'yearly' => ['price' => env('STRIPE_PRICE_PRO_YEARLY'), 'amount' => '$290/yr'],
        ],
        'business' => [
            'monthly' => ['price' => env('STRIPE_PRICE_BUSINESS_MONTHLY'), 'amount' => '$99/mo'],
            'yearly' => ['price' => env('STRIPE_PRICE_BUSINESS_YEARLY'), 'amount' => '$990/yr'],
        ],
    ],

];
