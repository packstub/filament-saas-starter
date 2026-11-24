<?php

use Illuminate\Support\Facades\File;

/*
 * docs/deploy/environment.md is what people set production from, so it
 * must list every key of .env.example and only keys the app really reads.
 */

/**
 * The variables in the first column of the reference's tables.
 *
 * @return list<string>
 */
function documentedVariables(): array
{
    preg_match_all('/^\| `([A-Z][A-Z0-9_]+)` \|/m', File::get(base_path('docs/deploy/environment.md')), $matches);

    return $matches[1];
}

/**
 * @return list<string>
 */
function exampleVariables(): array
{
    preg_match_all('/^#?\s?([A-Z][A-Z0-9_]+)=/m', File::get(base_path('.env.example')), $matches);

    return array_values(array_unique($matches[1]));
}

/**
 * What the config files read with env(): the kit's, and the defaults it does not publish (the framework's, Cashier's,
 * and those of the Packstub plugins a module recipe adds).
 *
 * @return list<string>
 */
function variablesReadByConfig(): array
{
    $files = collect(File::files(config_path()))
        ->merge(File::glob(base_path('vendor/laravel/framework/config/*.php')))
        ->merge(File::glob(base_path('vendor/laravel/cashier/config/*.php')))
        ->merge(File::glob(base_path('vendor/packstub/*/config/*.php')))
        ->map(fn ($file): string => File::get((string) $file))
        ->implode("\n");

    preg_match_all("/env\\('([A-Z][A-Z0-9_]+)'/", $files, $matches);

    return array_values(array_unique($matches[1]));
}

it('documents every variable of .env.example', function () {
    expect(array_values(array_diff(exampleVariables(), documentedVariables())))->toBe([]);
});

it('documents only variables the app reads', function () {
    // Read by Vite at build time, and by `php artisan serve`.
    $outsideConfig = ['VITE_APP_NAME', 'PHP_CLI_SERVER_WORKERS'];

    expect(array_values(array_diff(documentedVariables(), variablesReadByConfig(), $outsideConfig)))->toBe([]);
});

it('documents each variable once', function () {
    $documented = documentedVariables();

    expect(array_values(array_unique(array_diff_assoc($documented, array_unique($documented)))))->toBe([]);
});
