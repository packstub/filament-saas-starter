<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

/**
 * The classes and methods of the app a piece of Markdown names that do not
 * exist. A model's static call counts only when the model, its query
 * builder or one of its scopes has the method: Eloquent's __callStatic
 * would accept any name.
 *
 * @return list<string>
 */
function missingAppReferences(string $markdown): array
{
    $classes = collect(File::allFiles(app_path()))
        ->mapWithKeys(fn ($file): array => [$file->getFilenameWithoutExtension() => 'App\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname())]);

    $missing = [];

    foreach (codeSpans($markdown) as $span) {
        if (preg_match_all('/\b(App(?:\\\\\w+)+)/', $span, $matches)) {
            foreach ($matches[1] as $class) {
                $namespace = app_path(str_replace('\\', '/', Str::after($class, 'App\\')));

                if (! class_exists($class) && ! interface_exists($class) && ! enum_exists($class) && ! trait_exists($class) && ! is_dir($namespace)) {
                    $missing[] = $class;
                }
            }
        }

        if (preg_match_all('/\b(\w+)::(\w+)\(/', $span, $matches, PREG_SET_ORDER)) {
            foreach ($matches as [, $name, $method]) {
                $class = $classes->get($name);

                if ($class === null || method_exists($class, $method)) {
                    continue;
                }

                $exists = is_subclass_of($class, Model::class)
                    ? method_exists(Builder::class, $method) || method_exists($class, 'scope'.ucfirst($method))
                    : is_callable([$class, $method]);

                if (! $exists) {
                    $missing[] = "{$name}::{$method}()";
                }
            }
        }
    }

    return $missing;
}

it('names only classes and methods of the app that exist', function () {
    $missing = collect(agentDocuments())
        ->flatMap(fn (string $markdown, string $document): array => array_map(fn (string $reference): string => "{$document}: {$reference}", missingAppReferences($markdown)))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('notices a method a guideline makes up, even on a model', function () {
    expect(missingAppReferences('Use `Workspace::purgeEverything()`, `User::where()` and `App\Models\Nope`.'))
        ->toEqualCanonicalizing(['App\Models\Nope', 'Workspace::purgeEverything()']);
});
