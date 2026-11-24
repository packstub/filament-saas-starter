<?php

// The lines on the pricing cards (App\Support\Pricing), by feature key in config/packstub-features.php.
// A feature without a line here falls back to its label and unit from the config.
return [

    // `:count` is the plan's limit.
    'limits' => [
        'projects' => '{1} :count project|[0,*] :count projects',
        'seats' => '{1} :count seat|[0,*] :count seats',
    ],

    'unlimited_limits' => [
        'projects' => 'Unlimited projects',
        'seats' => 'Unlimited seats',
    ],

    // For a limit without a line above: `:unit` is the feature's unit from the config.
    'unlimited' => 'Unlimited :unit',

    'features' => [
        'api_access' => 'API access',
        'priority_support' => 'Priority support',
    ],

];
