<?php

/*
 * Applies a module recipe (docs/modules/<name>.md) to a copy of the kit, the
 * way a person following it would. Maintainers only: scripts/verify-module.sh
 * runs it. A new project does not get this file.
 *
 *     php scripts/apply-recipe.php docs/modules/flow.md /path/to/copy
 *
 * Only fenced code blocks with a directive after the language are applied, in
 * the order they appear; every other block is there to read. GitHub shows
 * the language and ignores the rest of the line.
 *
 *     ```bash run                                   run it in the copy's root
 *     ```php file=app/Listeners/X.php               write the whole file
 *     ```php append=routes/console.php              add it at the end of a file
 *     ```php in=app/Filament/Plugins.php before="function app()|];"
 *                                                   insert it before the line holding "];",
 *                                                   the first one after "function app()"
 *     ```php in=… after="A|B"                       insert it after the line holding B
 *     ```php in=… replace="A|B"                     put it in place of the line holding B
 *     ```php in=… replace="A" through="],"          … of the lines from A to the next one that is just "],"
 *     ```php use=app/Providers/AppServiceProvider.php
 *                                                   `use` lines, added after the file's last one
 *
 * An anchor is one or more strings separated by "|": each is looked for from
 * the line after the previous one. An inserted block takes the indentation of
 * the line above it (`before`), of the anchor's line (`after`; of the next
 * line when the anchor opens a block), or of the line it replaces (`replace`).
 */
if ($argc !== 3) {
    fwrite(STDERR, "Usage: php scripts/apply-recipe.php <recipe.md> <directory>\n");
    exit(2);
}

[, $recipe, $root] = $argv;
$root = rtrim(realpath($root) ?: $root, '/');

if (! is_file($recipe) || ! is_dir($root)) {
    fwrite(STDERR, "No recipe at {$recipe}, or no directory at {$root}.\n");
    exit(2);
}

$applied = 0;

try {
    $blocks = blocks(file_get_contents($recipe));
} catch (RuntimeException $exception) {
    fwrite(STDERR, basename($recipe).": {$exception->getMessage()}\n");
    exit(1);
}

foreach ($blocks as $block) {
    $directives = $block['directives'];

    if ($directives === []) {
        continue;
    }

    $applied++;
    $where = basename($recipe).':'.$block['line'];

    try {
        match (true) {
            isset($directives['run']) => run($block['body'], $root),
            isset($directives['file']) => write($root.'/'.$directives['file'], $block['body']),
            isset($directives['append']) => append($root.'/'.$directives['append'], $block['body']),
            isset($directives['use']) => addImports($root.'/'.$directives['use'], $block['body']),
            isset($directives['in']) => insert($root.'/'.$directives['in'], $block['body'], $directives),
            default => throw new RuntimeException('Unknown directive: '.implode(' ', array_keys($directives))),
        };
    } catch (Throwable $exception) {
        fwrite(STDERR, "\n{$where}: {$exception->getMessage()}\n");
        exit(1);
    }

    echo "  applied {$where} (".describe($directives).")\n";
}

if ($applied === 0) {
    fwrite(STDERR, "{$recipe} has no block to apply.\n");
    exit(1);
}

/**
 * @return list<array{line: int, directives: array<string, string|true>, body: string}>
 */
function blocks(string $markdown): array
{
    $blocks = [];
    $lines = explode("\n", $markdown);

    for ($i = 0; $i < count($lines); $i++) {
        if (! preg_match('/^(`{3,})(\S*)\s*(.*)$/', $lines[$i], $open)) {
            // Indented (inside a list item, say), a block would be read but never applied.
            if (preg_match('/^\s+`{3,}\S*\s+(run|file=|append=|use=|in=)/', $lines[$i])) {
                throw new RuntimeException('Line '.($i + 1).' has an indented code block with a directive: start it at the line\'s beginning.');
            }

            continue;
        }

        $body = [];
        $start = $i + 1;

        for ($i++; $i < count($lines) && ! preg_match('/^'.$open[1].'\s*$/', $lines[$i]); $i++) {
            $body[] = $lines[$i];
        }

        $blocks[] = ['line' => $start, 'directives' => directives($open[3]), 'body' => implode("\n", $body)."\n"];
    }

    return $blocks;
}

/**
 * @return array<string, string|true>
 */
function directives(string $info): array
{
    preg_match_all('/(\w+)(?:=("([^"]*)"|\S+))?/', $info, $matches, PREG_SET_ORDER);

    $directives = [];

    foreach ($matches as $match) {
        $directives[$match[1]] = match (true) {
            ! isset($match[2]) || $match[2] === '' => true,
            str_starts_with($match[2], '"') => $match[3] ?? '',
            default => $match[2],
        };
    }

    return $directives;
}

function describe(array $directives): string
{
    return implode(' ', array_map(fn ($key, $value) => $value === true ? $key : "{$key}={$value}", array_keys($directives), $directives));
}

function run(string $script, string $root): void
{
    $process = proc_open(['bash', '-euo', 'pipefail', '-c', $script], [STDIN, STDOUT, STDERR], $pipes, $root);

    if (proc_close($process) !== 0) {
        throw new RuntimeException("This command failed:\n{$script}");
    }
}

function write(string $path, string $contents): void
{
    if (file_exists($path)) {
        throw new RuntimeException("{$path} already exists: a recipe's new file must be new.");
    }

    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $contents);
}

function append(string $path, string $contents): void
{
    $current = existing($path);

    file_put_contents($path, rtrim($current, "\n")."\n\n".$contents);
}

function addImports(string $path, string $imports): void
{
    $lines = explode("\n", existing($path));
    $last = null;

    foreach ($lines as $index => $line) {
        if (preg_match('/^(use |namespace )/', $line)) {
            $last = $index;
        }
    }

    if ($last === null) {
        throw new RuntimeException("{$path} has no namespace or use line to add imports after.");
    }

    $new = array_values(array_filter(
        array_map('trim', explode("\n", trim($imports))),
        fn (string $import): bool => $import !== '' && ! in_array($import, $lines, true),
    ));

    // After a namespace line the imports start one blank line below.
    if (str_starts_with($lines[$last], 'namespace ')) {
        $new = ['', ...$new];
    }

    array_splice($lines, $last + 1, 0, $new);
    file_put_contents($path, implode("\n", $lines));
}

function insert(string $path, string $block, array $directives): void
{
    $lines = explode("\n", existing($path));
    $mode = array_values(array_filter(['before', 'after', 'replace'], fn (string $mode): bool => isset($directives[$mode])))[0]
        ?? throw new RuntimeException('`in` needs `before`, `after` or `replace`.');
    $anchors = explode('|', (string) $directives[$mode]);
    $at = -1;

    foreach ($anchors as $anchor) {
        for ($at++; $at < count($lines) && ! str_contains($lines[$at], $anchor); $at++);

        if ($at >= count($lines)) {
            throw new RuntimeException("{$path} has no line holding \"{$anchor}\" where the recipe expects it.");
        }
    }

    $like = match ($mode) {
        'before' => $at - 1,
        'replace' => $at,
        // Inside a block the anchor opens, like the next line; otherwise like the anchor.
        'after' => preg_match('/[\[({]\s*$/', $lines[$at]) ? (nextFilledLine($lines, $at + 1) ?? $at) : $at,
    };
    preg_match('/^\s*/', $lines[$like], $indent);

    $replaced = 0;

    if ($mode === 'replace') {
        for ($end = $at; isset($directives['through']) && $end < count($lines) && trim($lines[$end]) !== $directives['through']; $end++);

        if ($end >= count($lines)) {
            throw new RuntimeException("{$path} has no line that is just \"{$directives['through']}\" after \"{$anchor}\".");
        }

        $replaced = $end - $at + 1;
    }

    array_splice($lines, $mode === 'after' ? $at + 1 : $at, $replaced, reindent($block, $indent[0]));
    file_put_contents($path, implode("\n", $lines));
}

function nextFilledLine(array $lines, int $from): ?int
{
    for ($at = $from; $at < count($lines); $at++) {
        if (trim($lines[$at]) !== '') {
            return $at;
        }
    }

    return null;
}

/**
 * @return list<string>
 */
function reindent(string $block, string $indent): array
{
    $lines = explode("\n", rtrim($block, "\n"));
    $common = min(array_map(
        fn (string $line): int => strlen($line) - strlen(ltrim($line)),
        array_filter($lines, fn (string $line): bool => trim($line) !== ''),
    ) ?: [0]);

    return array_map(fn (string $line): string => trim($line) === '' ? '' : $indent.substr($line, $common), $lines);
}

function existing(string $path): string
{
    if (! is_file($path)) {
        throw new RuntimeException("{$path} does not exist.");
    }

    return file_get_contents($path);
}
