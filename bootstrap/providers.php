<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AppPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    // Last on purpose: console commands, queued jobs and webhooks run with the settings of the panel registered last.
    AppPanelProvider::class,
];
