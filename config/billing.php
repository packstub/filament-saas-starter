<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe prices
    |--------------------------------------------------------------------------
    |
    | One recurring Stripe price per paid plan, keyed by the plan's key in
    | config/packstub-features.php. A plan without a price here (such as
    | `free`) is never sold through Stripe.
    |
    | Checkout subscribes a workspace to one of these prices; the webhook
    | reads the subscription's price back into a plan key and saves it on
    | the workspace, which is what the limits are resolved from.
    |
    | One price per plan: to sell a plan monthly and yearly, give this file
    | a list of prices per plan and teach App\Billing\StripePrices to read
    | it. A subscription on a price that is not listed here keeps the
    | workspace on its current plan and writes a warning to the log.
    |
    */

    'prices' => [
        'pro' => env('STRIPE_PRICE_PRO'),
        'business' => env('STRIPE_PRICE_BUSINESS'),
    ],

];
