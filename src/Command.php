<?php

namespace ClaudioDekker\LaravelGuardrails;

use Composer\InstalledVersions;
use InvalidArgumentException;
use OutOfBoundsException;
use RuntimeException;
use Throwable;

/**
 * @internal
 */
class Command
{
    /**
     * The usage line printed with an unknown argument.
     */
    protected const USAGE = 'Usage: guardrails [--staged | --all | --base=<ref>] [--help] [--version], guardrails list [--details], or guardrails install [--preset=<name>] [--check=<class>]...';

    /**
     * The package the version comes from.
     */
    protected const PACKAGE = 'claudiodekker/laravel-guardrails';

    /**
     * The line printed when the run read the commits and the working tree or index holds changes it did not.
     */
    protected const UNREAD_CHANGES = 'Guardrails read the commits only, so the uncommitted changes were not checked. Commit them, or run guardrails --staged.';

    /**
     * The fix for a shallow clone, which lacks the history to find where HEAD branched off.
     */
    protected const FETCH_HISTORY = 'Fetch the full history, as fetch-depth: 0 does for actions/checkout.';

    /**
     * The fix for a shallow clone that has no origin/HEAD to compare against, which fetching the history does not create.
     */
    protected const FETCH_HISTORY_AND_BASE = 'Fetch the full history, as fetch-depth: 0 does for actions/checkout, then pass --base=origin/<branch>, or run git remote set-head origin --auto.';

    /**
     * Run the checks from the given directory with the given arguments, writing to the streams, and get the exit code.
     *
     * @param  list<string>  $arguments
     * @param  resource  $output
     * @param  resource  $errorOutput
     */
    public static function run(string $directory, array $arguments, $output, $errorOutput): int
    {
        try {
            return match ($arguments[0] ?? null) {
                'install' => InstallCommand::run($directory, array_slice($arguments, 1), $output, $errorOutput),
                'list' => ListCommand::run($directory, array_slice($arguments, 1), $output, $errorOutput),
                default => static::check($directory, $arguments, $output, $errorOutput),
            };
        } catch (InvalidArgumentException|RuntimeException $exception) {
            fwrite($errorOutput, $exception->getMessage()."\n");

            return 2;
        } catch (Throwable $exception) {
            fwrite($errorOutput, 'Guardrails crashed at '.Failure::location($exception, $directory).": {$exception->getMessage()}\n");

            return 2;
        }
    }

    /**
     * Run the checks guardrails.php selects over the changes the arguments ask for, writing the report to the streams, and get the exit code.
     *
     * @param  list<string>  $arguments
     * @param  resource  $output
     * @param  resource  $errorOutput
     *
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    protected static function check(string $directory, array $arguments, $output, $errorOutput): int
    {
        if (in_array('--help', $arguments, true)) {
            fwrite($output, static::USAGE."\n");

            return 0;
        }

        if (in_array('--version', $arguments, true)) {
            fwrite($output, static::version()."\n");

            return 0;
        }

        $error = static::refusal($arguments);

        if ($error !== null) {
            fwrite($errorOutput, "{$error} ".static::USAGE."\n");

            return 2;
        }

        $repository = Repository::fromDirectory($directory);
        $staged = in_array('--staged', $arguments, true);
        $configuration = $staged ? Configuration::fromIndex($repository->staged(), $repository->directory) : Configuration::fromProject($repository->directory);

        if ($configuration->checks === []) {
            return static::nothingSelected($output, $errorOutput);
        }

        $checks = $configuration->checks(Catalog::discover($repository->directory));
        $runner = new Runner($checks, $repository->directory);

        try {
            $changes = static::changes($repository, $arguments);
        } catch (MissingBase $exception) {
            return static::missingBase($repository, $exception, $arguments, $output, $errorOutput);
        }

        $report = $runner->run($changes);
        $report = new Report([...$report->failures, ...$runner->stale($repository->trackedPaths())], $report->files);
        $readsCommits = ! $staged && ! in_array('--all', $arguments, true);
        $unread = $readsCommits && $repository->hasUncommittedChanges();

        if ($report->passed()) {
            fwrite($output, 'Guardrails passed: '.static::count(count($checks), 'check').' over '.static::count($report->files, 'file').' ('.static::scope($arguments).").\n");
        } else {
            fwrite($errorOutput, $report->render());
        }

        if ($unread) {
            fwrite($errorOutput, static::UNREAD_CHANGES."\n");
        }

        return $report->exitCode();
    }

    /**
     * Say that no check is selected, and get the exit code: a failure on CI, where a run that checks nothing must not pass, else a pass.
     *
     * @param  resource  $output
     * @param  resource  $errorOutput
     */
    protected static function nothingSelected($output, $errorOutput): int
    {
        if (Environment::isCi()) {
            fwrite($errorOutput, Configuration::HINT."\n");

            return 2;
        }

        fwrite($output, Configuration::HINT."\n");

        return 0;
    }

    /**
     * Get the number with its noun, in the plural unless the number is one.
     */
    public static function count(int $number, string $noun): string
    {
        return $number === 1 ? "1 {$noun}" : "{$number} {$noun}s";
    }

    /**
     * Get why the arguments are refused, or null when they ask for one mode at most.
     *
     * @param  list<string>  $arguments
     */
    protected static function refusal(array $arguments): ?string
    {
        $modes = [];

        foreach ($arguments as $argument) {
            if ($argument === '--base=') {
                return '--base needs a ref, such as --base=origin/main.';
            }

            if (! in_array($argument, ['--all', '--staged'], true) && ! str_starts_with($argument, '--base=')) {
                return "Unknown argument {$argument}.";
            }

            $mode = str_starts_with($argument, '--base=') ? '--base' : $argument;

            if (in_array($mode, $modes, true)) {
                return "Pass {$mode} once.";
            }

            $modes[] = $mode;
        }

        return count($modes) > 1 ? 'Use only one of --staged, --all and --base=<ref>.' : null;
    }

    /**
     * Get the installed version of the package, or "unknown" when Composer does not know it.
     */
    protected static function version(): string
    {
        try {
            return InstalledVersions::getPrettyVersion(static::PACKAGE) ?? 'unknown';
        } catch (OutOfBoundsException) {
            return 'unknown';
        }
    }

    /**
     * Get what the arguments make the run check: the staged changes, every tracked file, or the range from the base to HEAD.
     *
     * @param  list<string>  $arguments
     */
    protected static function scope(array $arguments): string
    {
        return match (true) {
            in_array('--staged', $arguments, true) => 'staged changes',
            in_array('--all', $arguments, true) => 'every tracked file',
            default => (static::base($arguments) ?? 'origin/HEAD').'..HEAD',
        };
    }

    /**
     * Say that there is no base to compare against, with the fix, and get the exit code: a failure when the arguments name the base or the run is on CI, else a pass that checked nothing.
     *
     * @param  list<string>  $arguments
     * @param  resource  $output
     * @param  resource  $errorOutput
     */
    protected static function missingBase(Repository $repository, MissingBase $exception, array $arguments, $output, $errorOutput): int
    {
        $shallow = $repository->isShallow();

        if (static::base($arguments) !== null) {
            $message = $shallow ? "{$exception->getMessage()} ".static::FETCH_HISTORY : $exception->getMessage();

            fwrite($errorOutput, $message."\n");

            return 2;
        }

        $fix = match (true) {
            $shallow && $exception->refMissing => static::FETCH_HISTORY_AND_BASE,
            $shallow => static::FETCH_HISTORY,
            default => 'Pass --base=<ref> to compare against another branch.',
        };

        $line = "Guardrails checked nothing: {$exception->getMessage()} {$fix}\n";

        if (Environment::isCi()) {
            fwrite($errorOutput, $line);

            return 2;
        }

        fwrite($output, $line);

        return 0;
    }

    /**
     * Get the changes the arguments ask for: every tracked file, the staged diff, or the diff since the base branch.
     *
     * @param  list<string>  $arguments
     * @return iterable<non-empty-list<Change>>
     *
     * @throws MissingBase
     */
    protected static function changes(Repository $repository, array $arguments): iterable
    {
        if (in_array('--all', $arguments, true)) {
            return $repository->trackedChanges();
        }

        if (in_array('--staged', $arguments, true)) {
            return $repository->stagedChanges();
        }

        return $repository->changesSince($repository->mergeBase(static::base($arguments) ?? 'origin/HEAD'));
    }

    /**
     * Get the ref the arguments give with --base, or null when they give none.
     *
     * @param  list<string>  $arguments
     */
    protected static function base(array $arguments): ?string
    {
        $base = null;

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--base=')) {
                $base = substr($argument, strlen('--base='));
            }
        }

        return $base;
    }
}
