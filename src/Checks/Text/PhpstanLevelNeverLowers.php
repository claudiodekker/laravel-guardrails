<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Text;

use ClaudioDekker\LaravelGuardrails\Change;
use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;

/**
 * @api
 */
class PhpstanLevelNeverLowers extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'The PHPStan level never goes down.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A lower level makes PHPStan report fewer kinds of error, so lowering it hides errors instead of fixing them. This reads the level in phpstan.neon, phpstan.neon.dist and phpstan.dist.neon, where max counts as higher than every number. It flags the level line when a change lowers it, and the file when a change removes a level that was there, or deletes the file. Raising the level, or leaving it, passes. Keep the level and fix the errors it reports.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Keep the PHPStan level where it was, and fix the errors that level reports instead of lowering it.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return [Kind::TEXT];
    }

    /**
     * Get the patterns of the names of the files the check reads, which are PHPStan's configuration files.
     *
     * @return list<string>
     */
    protected function files(): array
    {
        return ['phpstan.neon', 'phpstan.neon.dist', 'phpstan.dist.neon'];
    }

    /**
     * Get no violations of the file alone, because the rule compares the level with the one before.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return [];
    }

    /**
     * Get a violation at the level line when the change lowers the level, or of the whole file when it removes a level the file had.
     *
     * @return list<Violation>
     */
    protected function violationsOf(Change $change): array
    {
        $before = $change->isAdded() ? null : $this->levelOf($change->previous);

        if ($before === null) {
            return [];
        }

        $after = $change->isDeleted() ? null : $this->levelOf($change->file);

        if ($after === null) {
            return [Violation::file()];
        }

        return $after['level'] < $before['level'] ? [Violation::line($after['line'])] : [];
    }

    /**
     * Get the level the file sets and the line it sets it on, or null when it sets none. The level max counts as higher than every number.
     *
     * @return array{level: int, line: int}|null
     */
    protected function levelOf(File $configuration): ?array
    {
        foreach (preg_split('/\R/', $configuration->contents) ?: [] as $index => $line) {
            if (preg_match('/^\s*level:\s*[\'"]?(\d+|max)[\'"]?\s*(#.*)?$/', $line, $match) === 1) {
                return ['level' => $match[1] === 'max' ? PHP_INT_MAX : (int) $match[1], 'line' => $index + 1];
            }
        }

        return null;
    }
}
