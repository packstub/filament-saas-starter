<?php

use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;

// Pest's presets: no debugging leftovers, no weak hashing or `eval`, and Laravel's
// naming and structure (https://pestphp.com/docs/arch-testing#presets).
arch()->preset()->php();
arch()->preset()->security();
// Filament's panel providers are named after their panel, without the `ServiceProvider` suffix.
arch()->preset()->laravel()->ignoring('App\Providers\Filament');

arch('models extend Eloquent')
    ->expect('App\Models')
    ->toExtend(Model::class);

arch('enums are enums that carry their own labels')
    ->expect('App\Enums')
    ->toBeEnums()
    ->toImplement(HasLabel::class);

arch('policies are plain classes named after their model')
    ->expect('App\Policies')
    ->toBeClasses()
    ->toHaveSuffix('Policy');

arch('support classes and actions are final')
    ->expect(['App\Support', 'App\Billing', 'App\Actions'])
    ->toBeFinal();

arch('the app reads its settings from config files, never from the environment')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('the models do not know about the panels that show them')
    ->expect('App\Models')
    ->not->toUse('App\Filament');
