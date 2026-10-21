<?php

namespace ClaudioDekker\LaravelGuardrails;

use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * @api
 */
abstract class ForbiddenCalls extends Check
{
    /**
     * The number of calls the description names before counting the rest.
     */
    protected const NAMED = 3;

    /**
     * The functions the rule forbids, by name, or by their full name for a function in a namespace.
     *
     * @var list<string>
     */
    protected array $functions = [];

    /**
     * The methods the rule forbids on any object, by name.
     *
     * @var list<string>
     */
    protected array $methods = [];

    /**
     * The static calls the rule forbids: the methods, by name, under the class they are called on.
     *
     * @var array<class-string|string, list<string>>
     */
    protected array $staticCalls = [];

    /**
     * Create the rule, which must list a call to forbid.
     *
     * @throws InvalidArgumentException
     */
    public function __construct()
    {
        if (
            $this->functions === []
            && $this->methods === []
            && $this->staticCalls === []
        ) {
            throw new InvalidArgumentException(static::name().' forbids nothing. List a function, a method or a static call.');
        }
    }

    /**
     * Get the one-line description of the rule, naming the calls it forbids.
     */
    public function description(): string
    {
        return 'No '.$this->sentence($this->calls()).'.';
    }

    /**
     * Get a violation at the name of each forbidden call, naming the call before the fix.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $violations = [];

        foreach ($file->find(fn (Node $node) => $this->isForbidden($node)) as $call) {
            $code = $this->callOf($call);

            $violations[] = Violation::at($this->nameOf($call) ?? $call, $code === null ? null : $this->messageFor($code));
        }

        return $violations;
    }

    /**
     * Get the message of a violation on the call, naming the call before the fix when the rule gives one.
     */
    protected function messageFor(string $code): string
    {
        $fix = $this->message();

        return $fix === null ? "{$code} is forbidden." : "{$code} is forbidden. {$fix}";
    }

    /**
     * Get the name of the call, or null for a node that is not a call.
     */
    protected function nameOf(Node $call): ?Node
    {
        if (
            $call instanceof FuncCall
            || $call instanceof MethodCall
            || $call instanceof NullsafeMethodCall
            || $call instanceof StaticCall
        ) {
            return $call->name;
        }

        return null;
    }

    /**
     * Get the call as code, as app(), ->table() or DB::table(), or null for a node that is not one of those calls.
     */
    protected function callOf(Node $call): ?string
    {
        if ($call instanceof FuncCall && $call->name instanceof Name) {
            return $call->name->toString().'()';
        }

        if (
            $call instanceof StaticCall
            && $call->class instanceof Name
            && $call->name instanceof Identifier
        ) {
            return $call->class->getLast().'::'.$call->name->toString().'()';
        }

        if (($call instanceof MethodCall || $call instanceof NullsafeMethodCall) && $call->name instanceof Identifier) {
            return '->'.$call->name->toString().'()';
        }

        return null;
    }

    /**
     * Determine if the node is a call the rule forbids.
     */
    protected function isForbidden(Node $node): bool
    {
        if ($node instanceof FuncCall) {
            return $node->name instanceof Name && $this->isForbiddenFunction($node->name);
        }

        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            return $node->name instanceof Identifier && $this->isListed($node->name->toString(), $this->methods);
        }

        return $node instanceof StaticCall && $this->isForbiddenStaticCall($node);
    }

    /**
     * Determine if the function is forbidden, by its last name segment, or by its full name for an entry that holds a namespace.
     */
    protected function isForbiddenFunction(Name $name): bool
    {
        foreach ($this->functions as $function) {
            $function = ltrim($function, '\\');
            $called = str_contains($function, '\\') ? $name->toString() : $name->getLast();

            if (strcasecmp($function, $called) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the static call is forbidden, which is one of the listed methods on the class an entry names in full.
     */
    protected function isForbiddenStaticCall(StaticCall $call): bool
    {
        if (! $call->class instanceof Name || ! $call->name instanceof Identifier) {
            return false;
        }

        foreach ($this->staticCalls as $class => $methods) {
            if (strcasecmp(ltrim($class, '\\'), $call->class->toString()) === 0 && $this->isListed($call->name->toString(), $methods)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the name is one of the listed names, ignoring case.
     *
     * @param  list<string>  $names
     */
    protected function isListed(string $name, array $names): bool
    {
        return in_array(strtolower($name), array_map(strtolower(...), $names), true);
    }

    /**
     * Get the forbidden calls as code, as app(), ->table() and DB::table(), the functions first and the static calls last.
     *
     * @return list<string>
     */
    protected function calls(): array
    {
        $calls = [
            ...array_map(fn (string $function) => ltrim($function, '\\').'()', $this->functions),
            ...array_map(fn (string $method) => "->{$method}()", $this->methods),
        ];

        foreach ($this->staticCalls as $class => $methods) {
            $short = basename(str_replace('\\', '/', $class));

            array_push($calls, ...array_map(fn (string $method) => "{$short}::{$method}()", $methods));
        }

        return $calls;
    }

    /**
     * Join the calls into a sentence, as "a, b or c", naming the first few and counting the rest.
     *
     * @param  list<string>  $calls
     */
    protected function sentence(array $calls): string
    {
        if (count($calls) > static::NAMED) {
            return implode(', ', array_slice($calls, 0, static::NAMED)).' and '.(count($calls) - static::NAMED).' more';
        }

        $last = array_pop($calls);

        if ($last === null) {
            return '';
        }

        return $calls === [] ? $last : implode(', ', $calls).' or '.$last;
    }
}
