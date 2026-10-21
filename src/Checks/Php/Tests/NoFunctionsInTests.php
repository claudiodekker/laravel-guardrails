<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Tests;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\Stmt\Function_;

/**
 * @api
 */
class NoFunctionsInTests extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No named function declared in a test file.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'The test runner loads every test file into one process, so two test files that declare a function of the same name stop the whole run with a fatal redeclare error. This flags each named function in a file ending in Test.php under tests, and leaves closures, methods and tests/Pest.php alone. Move a shared helper into tests/Pest.php or a helper class.';
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
     * Get the patterns of the names of the files the check reads, which are the tests.
     *
     * @return list<string>
     */
    protected function files(): array
    {
        return ['*Test.php'];
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Move the function to tests/Pest.php or a helper class, because two test files declaring the same function is a fatal redeclare.';
    }

    /**
     * Get a violation at the name of each named function.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return array_map(fn (Function_ $function) => Violation::at($function->name), $file->findInstanceOf(Function_::class));
    }
}
