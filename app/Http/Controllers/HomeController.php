<?php

namespace App\Http\Controllers;

use App\Support\Pricing;
use Illuminate\View\View;

/**
 * The public home page, with the pricing read from the plans the app sells.
 */
class HomeController
{
    public function __invoke(): View
    {
        return view('site.home', [
            'plans' => Pricing::plans(),
            'intervals' => Pricing::intervals(),
            'trialDays' => Pricing::trialDays(),
        ]);
    }
}
