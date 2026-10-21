<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Tests;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;

/**
 * @api
 */
class TestsInUnitOrFeature extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'Every test sits in a Unit or Feature folder.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'Two folders, one for tests that run a class alone and one for tests that run the app, tell a reader what a test needs before they open it. This flags a test file that has no Unit or Feature folder in its path, at any depth. Files that do not end in Test.php and files in a Fixtures folder are left alone.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return [Kind::TESTS];
    }

    /**
     * Get the one-line description of the files the check reads.
     */
    public function scope(): string
    {
        return '*Test.php in tests, outside Fixtures folders';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Move the test into a Unit or Feature folder.';
    }

    /**
     * Get the patterns of the names of the files the check reads, which are the tests.
     *
     * @return list<string>
     */
    protected function files(): array
    {
        return ['*Test.php'];
    }

    /**
     * Determine if the check reads the file, which is any test outside a Fixtures folder.
     */
    protected function reads(File $file): bool
    {
        return parent::reads($file) && ! $file->isIn('Fixtures');
    }

    /**
     * Get a whole-file violation for a test outside every Unit and Feature folder.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return $file->isIn('Unit') || $file->isIn('Feature') ? [] : [Violation::file()];
    }
}
