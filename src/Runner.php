<?php

namespace ClaudioDekker\LaravelGuardrails;

use ClaudioDekker\LaravelGuardrails\Checks\Php\NoSyntaxErrors;
use InvalidArgumentException;
use Throwable;

/**
 * @internal
 */
class Runner
{
    /**
     * The checks to run, in order, ending in a check that every PHP file parses unless the selection holds one.
     *
     * @var list<Check>
     */
    public readonly array $checks;

    /**
     * The most files a crashing check is reported on one by one, before one line counts the rest.
     */
    protected const CRASHES_LISTED = 3;

    /**
     * The label each check's failures print under, by the check's index.
     *
     * @var list<string>
     */
    protected array $labels;

    /**
     * Create a runner of the given checks, each under a label of its own, refusing a check whose files() patterns name a folder before any file is read.
     *
     * @param  list<Check>  $checks
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $checks, protected readonly string $directory)
    {
        $this->checks = $this->withSyntaxCheck($checks);

        foreach ($this->checks as $check) {
            $check->ensurePatterns();
        }

        $this->labels = $this->labels($this->checks);
    }

    /**
     * Run every check over one changed file at a time, so a file's parsed code is released before the next, and report the violations they bring in and the checks that crash.
     *
     * @param  iterable<non-empty-list<Change>>  $changes
     */
    public function run(iterable $changes): Report
    {
        $failures = array_fill(0, count($this->checks), []);
        $crashes = array_fill(0, count($this->checks), 0);
        $files = 0;

        foreach ($changes as $perParent) {
            $files++;
            $path = $perParent[0]->path();

            foreach ($this->checks as $index => $check) {
                try {
                    foreach ($check->check(...$perParent) as $violation) {
                        $failures[$index][] = new Failure($this->labels[$index], $path, $violation, $check->messageOf($violation));
                    }
                } catch (Throwable $exception) {
                    if (++$crashes[$index] <= static::CRASHES_LISTED) {
                        $failures[$index][] = Failure::crash($this->labels[$index], $path, $exception, $this->directory);
                    }
                }
            }
        }

        foreach ($crashes as $index => $count) {
            if ($count > static::CRASHES_LISTED) {
                $failures[$index][] = Failure::crashSummary($this->labels[$index], $count - static::CRASHES_LISTED);
            }
        }

        return new Report(array_merge(...$failures), $files);
    }

    /**
     * Get a failure under each check for every path it allows that no given path is at or under, reading the paths only until each allowance has matched one.
     *
     * @param  list<string>  $paths
     * @return list<Failure>
     */
    public function stale(array $paths): array
    {
        $unmatched = [];

        foreach ($this->checks as $index => $check) {
            foreach ($check->allowances() as $allowed) {
                $unmatched[] = [$index, $allowed];
            }
        }

        foreach ($paths as $path) {
            if ($unmatched === []) {
                break;
            }

            $unmatched = array_filter($unmatched, fn (array $allowance) => ! Layout::holds($allowance[1], $path));
        }

        return array_values(array_map(
            fn (array $allowance) => new Failure($this->labels[$allowance[0]], $allowance[1], Violation::file(), 'allowing() names a path no file matches. Remove it from guardrails.php.'),
            $unmatched,
        ));
    }

    /**
     * Get the checks with a check that every PHP file parses after them, unless they already hold one.
     *
     * @param  list<Check>  $checks
     * @return list<Check>
     */
    protected function withSyntaxCheck(array $checks): array
    {
        foreach ($checks as $check) {
            if ($check instanceof NoSyntaxErrors) {
                return $checks;
            }
        }

        return [...$checks, new NoSyntaxErrors];
    }

    /**
     * Get the label of each check: the name as() gave it, else its name, or its full name when a check of another class prints under that name, refusing two entries that still share a label.
     *
     * @param  list<Check>  $checks
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function labels(array $checks): array
    {
        $labels = array_map(fn (Check $check) => $check->label() ?? $check::name(), $checks);
        $classes = array_map(fn (Check $check) => $check::class, $checks);
        $resolved = [];

        foreach ($checks as $index => $check) {
            $label = $check->label();

            if ($label !== null) {
                $resolved[] = $label;

                continue;
            }

            $sharedByAnotherClass = array_filter(array_keys($labels, $labels[$index], true), fn (int $other) => $classes[$other] !== $classes[$index]);

            $resolved[] = $sharedByAnotherClass === [] ? $labels[$index] : $check::fullName();
        }

        foreach ($resolved as $index => $label) {
            $first = array_search($label, $resolved, true);

            if ($first !== $index) {
                throw new InvalidArgumentException('guardrails.php: checks '.($first + 1).' and '.($index + 1)." are both labelled {$label}. Name one with ->as('...').");
            }
        }

        return $resolved;
    }
}
