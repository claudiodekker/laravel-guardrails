<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Safety;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\Concerns\SkipsFakeData;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;

/**
 * @api
 */
class NoDebugCalls extends Check
{
    use SkipsFakeData;

    /**
     * The functions that dump a value for debugging.
     */
    protected const FUNCTIONS = ['dd', 'ddd', 'dump', 'ray', 'var_dump', 'print_r'];

    /**
     * The methods that dump the object for debugging.
     */
    protected const METHODS = ['dd', 'ddd', 'dump'];

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No dd(), dump(), ray(), var_dump() or print_r() outside tests.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A dd() left in the code stops the request in production and shows the visitor whatever it dumped. This flags dd(), ddd(), dump(), ray(), var_dump() and print_r(), but not print_r($value, true), which returns a string, and ->dd(), ->ddd() and ->dump() on any object, such as a collection or a query. Files under tests are left alone, because a test may dump what it inspects, and so are the database factories and seeders, which make fake data.';
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
        return 'Remove the debug call before committing.';
    }

    /**
     * Get a violation at the name of each debug call, naming the call before the fix.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $calls = [
            ...$file->findInstanceOf(FuncCall::class),
            ...$file->findInstanceOf(MethodCall::class),
            ...$file->findInstanceOf(NullsafeMethodCall::class),
        ];

        $debugCalls = array_filter($calls, fn (FuncCall|MethodCall|NullsafeMethodCall $call) => $this->isDebugCall($call));

        return array_map(fn (FuncCall|MethodCall|NullsafeMethodCall $call) => Violation::at($call->name, $this->callOf($call).' is forbidden. '.$this->message()), array_values($debugCalls));
    }

    /**
     * Get the debug call as code, as dd() or ->dump().
     */
    protected function callOf(FuncCall|MethodCall|NullsafeMethodCall $call): string
    {
        $name = $call->name instanceof Node\Name || $call->name instanceof Identifier ? $call->name->toString() : '';

        return $call instanceof FuncCall ? "{$name}()" : "->{$name}()";
    }

    /**
     * Determine if the call is to a debug function, or a debug method on any object.
     */
    protected function isDebugCall(FuncCall|MethodCall|NullsafeMethodCall $call): bool
    {
        if ($call instanceof FuncCall) {
            return $call->name instanceof Node\Name
                && in_array($call->name->toLowerString(), static::FUNCTIONS, true)
                && ! $this->returnsString($call);
        }

        return $call->name instanceof Identifier && in_array($call->name->toLowerString(), static::METHODS, true);
    }

    /**
     * Determine if the call to print_r() passes true to return the output instead of printing it.
     */
    protected function returnsString(FuncCall $call): bool
    {
        if (! $call->name instanceof Node\Name || $call->name->toLowerString() !== 'print_r') {
            return false;
        }

        foreach ($call->args as $position => $argument) {
            if (
                $argument instanceof Node\Arg
                && ($argument->name?->toString() === 'return' || ($argument->name === null && $position === 1))
                && $argument->value instanceof Node\Expr\ConstFetch
                && $argument->value->name->toLowerString() === 'true'
            ) {
                return true;
            }
        }

        return false;
    }
}
