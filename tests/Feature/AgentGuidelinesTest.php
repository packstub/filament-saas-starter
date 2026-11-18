<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
 * AGENTS.md and the guidelines in .ai/ are what coding agents act on, so a
 * file, class or method they name that no longer exists sends an agent the
 * wrong way. These tests keep them in step with the code.
 */

/**
 * AGENTS.md without the block Laravel Boost writes into it (its own
 * guidelines, about Laravel in general), and the app's own guidelines and
 * skills.
 *
 * @return array<string, string> file name => contents
 */
function agentDocuments(): array
{
    $agents = preg_replace('#<laravel-boost-guidelines>.*</laravel-boost-guidelines>#s', '', File::get(base_path('AGENTS.md')));

    return collect(File::allFiles(base_path('.ai/guidelines')))
        ->merge(File::allFiles(base_path('.ai/skills')))
        ->mapWithKeys(fn ($file): array => [Str::after((string) $file, base_path().'/') => File::get((string) $file)])
        ->prepend($agents, 'AGENTS.md')
        ->all();
}

/**
 * The text between backticks, minus what is only an example: the made-up
 * `Invoice` of the adding-a-workspace-resource skill.
 *
 * @return list<string>
 */
function codeSpans(string $markdown): array
{
    preg_match_all('/`([^`\n]+)`/', $markdown, $matches);

    return array_values(array_filter($matches[1], fn (string $span): bool => ! Str::contains($span, ['Invoice', 'invoice'])));
}

it('has guidelines for the areas an agent changes', function () {
    expect(File::files(base_path('.ai/guidelines')))->not->toBeEmpty()
        ->and(base_path('.ai/skills/adding-a-workspace-resource/SKILL.md'))->toBeFile();
});

it('names only files and folders that exist', function () {
    $missing = [];

    foreach (agentDocuments() as $document => $markdown) {
        foreach (codeSpans($markdown) as $span) {
            $path = rtrim(Str::before($span, ' '), '/.,:');

            if (! preg_match('#^(app|bootstrap|config|database|docs|lang|resources|routes|tests)/[\w./-]+$#', $path)) {
                continue;
            }

            // A class may be named by its path without the extension.
            if (! file_exists(base_path($path)) && ! file_exists(base_path($path.'.php'))) {
                $missing[] = "{$document}: {$path}";
            }
        }
    }

    expect($missing)->toBe([]);
});

it('names only classes and methods of the app that exist', function () {
    $classes = collect(File::allFiles(app_path()))
        ->mapWithKeys(fn ($file): array => [$file->getFilenameWithoutExtension() => 'App\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname())]);

    $missing = [];

    foreach (agentDocuments() as $document => $markdown) {
        foreach (codeSpans($markdown) as $span) {
            if (preg_match_all('/\b(App(?:\\\\\w+)+)/', $span, $matches)) {
                foreach ($matches[1] as $class) {
                    $namespace = app_path(str_replace('\\', '/', Str::after($class, 'App\\')));

                    if (! class_exists($class) && ! interface_exists($class) && ! enum_exists($class) && ! trait_exists($class) && ! is_dir($namespace)) {
                        $missing[] = "{$document}: {$class}";
                    }
                }
            }

            if (preg_match_all('/\b(\w+)::(\w+)\(/', $span, $matches, PREG_SET_ORDER)) {
                foreach ($matches as [, $class, $method]) {
                    // is_callable() accepts what a model forwards to its query builder (`Workspace::where()`).
                    if ($classes->has($class) && ! method_exists($classes[$class], $method) && ! is_callable([$classes[$class], $method])) {
                        $missing[] = "{$document}: {$class}::{$method}()";
                    }
                }
            }
        }
    }

    expect($missing)->toBe([]);
});
