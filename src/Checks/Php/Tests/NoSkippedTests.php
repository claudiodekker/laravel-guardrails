<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Tests;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * @api
 */
class NoSkippedTests extends Check
{
    /**
     * The lowercase names of the PHPUnit methods that end a test without running its assertions.
     */
    protected const PHPUNIT_METHODS = ['marktestincomplete', 'marktestskipped'];

    /**
     * The lowercase names of the Pest methods that skip a test or mark it as still to write.
     */
    protected const PEST_METHODS = ['skip', 'todo'];

    /**
     * The lowercase names of the Pest functions that start a test or a group of tests.
     */
    protected const PEST_FUNCTIONS = ['describe', 'it', 'test'];

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No test is skipped or marked as a todo.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A skipped test still shows in the report but checks nothing, so skipping one is a way to get a failing suite to pass. This flags markTestSkipped() and markTestIncomplete() on any receiver, the skip() and todo() methods chained onto a Pest it(), test() or describe(), and Pest\'s todo() function. Other skip() calls, such as a collection\'s, pass. Fix the code or the test, or delete a test of behavior that no longer exists. List a test that cannot run in some environment with allowing().';
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
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Fix the code or the test so it passes, or delete the test if the behavior is gone, because a skipped test checks nothing.';
    }

    /**
     * Get a violation at the name of each call that skips a test or marks it as a todo.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $calls = [
            ...array_filter($file->findInstanceOf(MethodCall::class), $this->isSkipMethod(...)),
            ...array_filter($file->findInstanceOf(StaticCall::class), $this->isPhpUnitSkip(...)),
            ...array_filter($file->findInstanceOf(FuncCall::class), $this->isTodoFunction(...)),
        ];

        return array_map(fn (MethodCall|StaticCall|FuncCall $call) => Violation::at($call->name), $calls);
    }

    /**
     * Determine if the method call is PHPUnit's skip, or Pest's skip or todo chained onto a test.
     */
    protected function isSkipMethod(MethodCall $call): bool
    {
        return $this->isPhpUnitSkip($call)
            || ($this->isNamed($call, static::PEST_METHODS) && $this->startsInPestTest($call));
    }

    /**
     * Determine if the call is to markTestSkipped() or markTestIncomplete().
     */
    protected function isPhpUnitSkip(MethodCall|StaticCall $call): bool
    {
        return $this->isNamed($call, static::PHPUNIT_METHODS);
    }

    /**
     * Determine if the call is to Pest's todo() function.
     */
    protected function isTodoFunction(FuncCall $call): bool
    {
        return $this->isNamed($call, ['todo']);
    }

    /**
     * Determine if the call's name is one of the given lowercase names.
     *
     * @param  list<string>  $names
     */
    protected function isNamed(MethodCall|StaticCall|FuncCall $call, array $names): bool
    {
        return ($call->name instanceof Identifier || $call->name instanceof Name)
            && in_array($call->name->toLowerString(), $names, true);
    }

    /**
     * Determine if the chain of method calls the call ends starts at a Pest function that declares a test, which tells Pest's skip() from a collection's.
     */
    protected function startsInPestTest(MethodCall $call): bool
    {
        $receiver = $call->var;

        while ($receiver instanceof MethodCall) {
            $receiver = $receiver->var;
        }

        return $receiver instanceof FuncCall && $this->isNamed($receiver, static::PEST_FUNCTIONS);
    }
}
