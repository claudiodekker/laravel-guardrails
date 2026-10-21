<?php

use ClaudioDekker\LaravelGuardrails\Catalog;
use ClaudioDekker\LaravelGuardrails\Command;
use ClaudioDekker\LaravelGuardrails\ConfigurationFile;
use ClaudioDekker\LaravelGuardrails\InstallCommand;
use Laravel\Prompts\Prompt;
use Mockery\MockInterface;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Write a composer.json with the given text and get its path, which does not exist yet when the text is null.
 */
function composerJsonText(?string $contents): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'guardrails-composer-'.getmypid().'-');

    if ($contents === null) {
        unlink($path);
    } else {
        file_put_contents($path, $contents);
    }

    return $path;
}

/**
 * Make a project folder holding the given composer.json and the given installed packages, and get its path.
 *
 * @param  array<string, mixed>  $manifest
 * @param  list<array<string, mixed>>  $packages
 */
function project(array $manifest = [], array $packages = [], string $vendor = 'vendor'): string
{
    $path = sys_get_temp_dir().'/guardrails-project-'.getmypid().'-'.bin2hex(random_bytes(6));

    mkdir("{$path}/{$vendor}/composer", 0755, true);

    if ($manifest !== []) {
        file_put_contents("{$path}/composer.json", json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    file_put_contents("{$path}/{$vendor}/composer/installed.json", json_encode(['packages' => $packages, 'dev' => true, 'dev-package-names' => []], JSON_THROW_ON_ERROR));

    return $path;
}

/**
 * Make a project folder whose guardrails.php holds the given contents, with the given composer.json, and get its path.
 *
 * @param  array<string, mixed>  $manifest
 */
function projectSelecting(string $contents, array $manifest = ['name' => 'acme/app']): string
{
    $path = project($manifest);

    file_put_contents("{$path}/guardrails.php", $contents);

    return $path;
}

/**
 * Make a project folder holding the given files by their relative paths, and get its path.
 *
 * @param  array<string, string>  $files
 */
function projectWith(array $files): string
{
    $path = sys_get_temp_dir().'/guardrails-project-'.getmypid().'-'.bin2hex(random_bytes(6));

    mkdir($path, 0755, true);

    foreach ($files as $file => $contents) {
        if (! is_dir(dirname("{$path}/{$file}"))) {
            mkdir(dirname("{$path}/{$file}"), 0755, true);
        }

        file_put_contents("{$path}/{$file}", $contents);
    }

    return $path;
}

/**
 * Describe an installed package that provides the given checks and presets, as installed.json lists it.
 *
 * @param  list<string>  $checks
 * @param  array<string, list<string>>  $presets
 * @param  array<string, string>  $descriptions
 * @return array<string, mixed>
 */
function installedPack(string $name, array $checks, array $presets = [], array $descriptions = []): array
{
    return ['name' => $name, 'version' => '1.0.0', 'extra' => ['guardrails' => ['provides' => ['checks' => $checks, 'presets' => $presets, 'descriptions' => $descriptions]]]];
}

/**
 * Determine if any frame the faked prompts drew, read without escape sequences, passes the test.
 *
 * @param  callable(string): bool  $test
 */
function anyFrame(callable $test): bool
{
    $frames = explode("\e[J", Prompt::content());
    $stripped = array_map(fn (string $frame) => (string) preg_replace("/\e\[[0-9;?]*[A-Za-z]/", '', $frame), $frames);

    return array_filter($stripped, $test) !== [];
}

/**
 * Get the frames the faked tree prompt drew while it was being answered, read without escape sequences.
 *
 * @return list<string>
 */
function treeFrames(): array
{
    $frames = explode("\e[J", Prompt::content());
    $stripped = array_map(fn (string $frame) => (string) preg_replace("/\e\[[0-9;?]*[A-Za-z]/", '', $frame), $frames);

    return array_values(array_filter($stripped, fn (string $frame) => str_contains($frame, 'enter finish') && ! str_contains($frame, 'Start from')));
}

/**
 * Call the callback with the given environment variables set, restoring them afterwards.
 *
 * @template TReturn
 *
 * @param  array<string, string>  $variables
 * @param  callable(): TReturn  $callback
 * @return TReturn
 */
function withEnvironment(array $variables, callable $callback): mixed
{
    $previous = [];

    foreach ($variables as $name => $value) {
        $previous[$name] = getenv($name);

        putenv("{$name}={$value}");
    }

    try {
        return $callback();
    } finally {
        foreach ($previous as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }
}

/**
 * Get the environment variables that point git at a global configuration holding the given text, with the given variables on top.
 *
 * @param  array<string, string>  $variables
 * @return array<string, string>
 */
function hostileGit(string $configuration, array $variables = []): array
{
    return ['GIT_CONFIG_GLOBAL' => projectWith(['gitconfig' => $configuration]).'/gitconfig', ...$variables];
}

/**
 * Remove the folder and everything in it.
 */
function removeDirectory(string $path): void
{
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($path);
}

/**
 * Get the classes of the given built-in preset.
 *
 * @return list<string>
 */
function builtIn(string $preset): array
{
    return Catalog::discover(dirname(__DIR__, 2))->preset($preset)->checks;
}

/**
 * Read the classes the guardrails.php in the given folder selects.
 *
 * @return list<string>
 */
function guardrails(string $directory): array
{
    return ConfigurationFile::read($directory)->classes();
}

/**
 * Run the install command in the given folder, answering its prompts with the given keys on a terminal of the given height, and get its exit code.
 *
 * @param  list<string>  $keys
 */
function install(string $directory, array $keys, int $lines = 24): int
{
    Prompt::fake($keys);

    $terminal = Prompt::terminal();

    assert($terminal instanceof MockInterface);

    $terminal->allows(['lines' => $lines]);

    $output = fopen('php://memory', 'w+');
    $errorOutput = fopen('php://memory', 'w+');

    assert(is_resource($output) && is_resource($errorOutput));

    return InstallCommand::run($directory, [], $output, $errorOutput, interactive: true);
}

/**
 * Run the list command in the given folder with the given arguments and get its exit code and streams.
 *
 * @param  list<string>  $arguments
 * @return array{exitCode: int, output: string, errorOutput: string}
 */
function listChecks(string $directory, array $arguments = []): array
{
    $output = fopen('php://memory', 'w+');
    $errorOutput = fopen('php://memory', 'w+');

    assert(is_resource($output) && is_resource($errorOutput));

    $exitCode = Command::run($directory, ['list', ...$arguments], $output, $errorOutput);

    rewind($output);
    rewind($errorOutput);

    return ['exitCode' => $exitCode, 'output' => (string) stream_get_contents($output), 'errorOutput' => (string) stream_get_contents($errorOutput)];
}

/**
 * Parse the code and get its nodes of the given class, in order.
 *
 * @template TNode of Node
 *
 * @param  class-string<TNode>  $class
 * @return list<TNode>
 */
function nodesOf(string $code, string $class): array
{
    $statements = (new ParserFactory)->createForHostVersion()->parse($code) ?? [];

    return array_values((new NodeFinder)->findInstanceOf($statements, $class));
}

/**
 * Get the guardrails.php whose KeepMigrations check fails the deletion of a migration.
 */
function keepMigrations(): string
{
    return <<<'PHP'
        <?php

        use ClaudioDekker\LaravelGuardrails\Change;
        use ClaudioDekker\LaravelGuardrails\Check;
        use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;
        use ClaudioDekker\LaravelGuardrails\File;
        use ClaudioDekker\LaravelGuardrails\Kind;
        use ClaudioDekker\LaravelGuardrails\Violation;

        return [
            NoEmDashes::class,
            (new class extends Check
            {
                public function description(): string
                {
                    return 'A committed migration is never deleted.';
                }

                public function message(): string
                {
                    return 'Add a new migration instead, because this one may already have run.';
                }

                public function kinds(): array
                {
                    return [Kind::MIGRATIONS];
                }

                protected function violations(File $file): array
                {
                    return [];
                }

                protected function violationsOf(Change $change): array
                {
                    return $change->isDeleted() ? [Violation::file()] : [];
                }
            })->as('KeepMigrations'),
        ];
        PHP;
}

pest()->afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/guardrails-composer-'.getmypid().'-*') ?: [] as $path) {
        unlink($path);
    }

    foreach (glob(sys_get_temp_dir().'/guardrails-project-'.getmypid().'-*') ?: [] as $path) {
        removeDirectory($path);
    }
})->in('Unit', 'Feature');
