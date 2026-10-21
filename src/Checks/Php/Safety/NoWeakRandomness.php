<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Safety;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\Concerns\SkipsFakeData;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;

/**
 * @api
 */
class NoWeakRandomness extends Check
{
    use SkipsFakeData;

    /**
     * The functions the check refuses, lowercase.
     */
    protected const FUNCTIONS = ['mt_rand', 'rand', 'uniqid'];

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No rand(), mt_rand() or uniqid() outside tests.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'rand() and mt_rand() follow from their seed and uniqid() follows from the clock, so a token or code built from them can be guessed. This flags every call outside tests and the database factories and seeders, which make fake data, even where the value is not secret. Use random_int() for a number, and Str::random() or Str::uuid() for a token.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return Kind::except(Kind::TESTS);
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Use random_int() or Str::random() instead, because rand(), mt_rand() and uniqid() can be predicted.';
    }

    /**
     * Get a violation at the name of each call to rand(), mt_rand() or uniqid().
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $calls = array_filter($file->findInstanceOf(FuncCall::class), fn (FuncCall $call) => $this->isRefused($call));

        return array_map(fn (FuncCall $call) => Violation::at($call->name), array_values($calls));
    }

    /**
     * Determine if the call is to rand(), mt_rand() or uniqid().
     */
    protected function isRefused(FuncCall $call): bool
    {
        return $call->name instanceof Node\Name && in_array($call->name->toLowerString(), static::FUNCTIONS, true);
    }
}
