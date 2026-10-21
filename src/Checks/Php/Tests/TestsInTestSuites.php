<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Tests;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\PhpUnit;
use ClaudioDekker\LaravelGuardrails\TestSuite;
use ClaudioDekker\LaravelGuardrails\Violation;

/**
 * @api
 */
class TestsInTestSuites extends Check
{
    /**
     * The folders a project without a configuration file runs its tests from.
     */
    protected const DEFAULT_FOLDERS = ['tests/Unit', 'tests/Feature', 'tests/Browser'];

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'Every test sits in a folder a test suite of phpunit.xml runs, or in tests/Unit, tests/Feature or tests/Browser without one.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'PHPUnit and Pest run only the folders and files a test suite lists, so a test outside them never runs and its failures go unseen. This reads the test suites of phpunit.xml, or of phpunit.xml.dist when there is none, with their directories, files and excludes. Without either file, tests/Unit, tests/Feature and tests/Browser count. Files that do not end in Test.php and files in a Fixtures folder are left alone.';
    }

    /**
     * Get the kinds of code the check reads: the tests, and the files under no root, where a test no suite runs may sit.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return [Kind::TESTS, Kind::TEXT];
    }

    /**
     * Get the one-line description of the files the check reads.
     */
    public function scope(): string
    {
        return '*Test.php in tests and other files, outside Fixtures folders';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Move the test into '.$this->sentence(static::DEFAULT_FOLDERS).', or add a phpunit.xml whose test suites run its folder.';
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
     * Get a whole-file violation for a test no test suite runs, naming the folders the suites run.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $configuration = $file->project->phpUnit();
        $runs = $configuration === null ? (new TestSuite(static::DEFAULT_FOLDERS))->runs($file->path) : $configuration->runs($file->path);

        return $runs ? [] : [Violation::file($this->messageUnder($configuration))];
    }

    /**
     * Get the failure message under the PHPUnit configuration, naming the folders its test suites run, or the check's message without one.
     */
    protected function messageUnder(?PhpUnit $configuration): string
    {
        if ($configuration === null) {
            return $this->message();
        }

        if ($configuration->folders() === []) {
            return "Add the test's folder to a test suite in {$configuration->name}.";
        }

        return 'Move the test into '.$this->sentence($configuration->folders()).", or add its folder to a test suite in {$configuration->name}.";
    }

    /**
     * Join the folders into a sentence, as "a, b or c".
     *
     * @param  non-empty-list<string>  $folders
     */
    protected function sentence(array $folders): string
    {
        $last = array_pop($folders);

        return $folders === [] ? $last : implode(', ', $folders).' or '.$last;
    }
}
