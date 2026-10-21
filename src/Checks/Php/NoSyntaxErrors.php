<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\SyntaxError;
use ClaudioDekker\LaravelGuardrails\Violation;

/**
 * @api
 */
class NoSyntaxErrors extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'Every PHP file parses.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'No check can read a PHP file that does not parse, so the run fails on it whatever the change did. This runs whether or not guardrails.php lists it. List it to allow a folder of files that are broken on purpose, such as parser fixtures.';
    }

    /**
     * Get the one-line description of the files the check reads.
     */
    public function scope(): string
    {
        return 'PHP';
    }

    /**
     * Determine if the check reads the file, which is any PHP file but a Blade template.
     */
    protected function reads(File $file): bool
    {
        return parent::reads($file) && $file->isPhp();
    }

    /**
     * Get a whole-file violation when the file does not parse, shown at the line of the error when the parser knows it, and naming it.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        try {
            $file->statements();
        } catch (SyntaxError $error) {
            $line = $error->startLine < 1 ? null : $error->startLine;

            return [Violation::file($error->getMessage(), line: $line)];
        }

        return [];
    }

    /**
     * Determine if the violation is new with the change, which any change to a file that does not parse is.
     */
    protected function isNewWholeFile(Violation $violation, File $file, File $previous): bool
    {
        return true;
    }
}
