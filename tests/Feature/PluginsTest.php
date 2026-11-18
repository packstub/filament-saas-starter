<?php

use App\Filament\Plugins;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;

it('registers the optional plugins on their panel', function (string $panel, Closure $plugins) {
    $registered = Filament::getPanel($panel)->getPlugins();

    foreach ($plugins() as $plugin) {
        expect($plugin)->toBeInstanceOf(Plugin::class)
            ->and($registered)->toHaveKey($plugin->getId());
    }

    // The kit's own plugins stay registered next to them.
    expect($registered)->toHaveKeys(['packstub-features', 'packstub-account-switcher']);
})->with([
    'app' => ['app', fn (): array => Plugins::app()],
    'admin' => ['admin', fn (): array => Plugins::admin()],
]);
