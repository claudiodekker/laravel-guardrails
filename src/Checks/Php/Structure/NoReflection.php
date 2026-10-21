<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Structure;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;

/**
 * @api
 */
class NoReflection extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No Reflection class and no Illuminate\\Support\\Reflector outside tests.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'Reflection reaches past the interface of a class, breaks when its internals change and hides the call from static analysis. This flags ReflectionClass, ReflectionMethod and the other Reflection classes, and the framework\'s Reflector, wherever a name is used. Files under tests are left alone. Call the code directly instead, or give the class a protected method to override.';
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
        return 'Call the code directly, or give it a protected method to override.';
    }

    /**
     * Get a violation at each use of a Reflection class or the framework's Reflector.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return array_map(Violation::at(...), $file->find(fn (Node $node) => $node instanceof Node\Name && $this->isReflection($node->toString())));
    }

    /**
     * Determine if the name is a Reflection class or the framework's Reflector.
     */
    protected function isReflection(string $name): bool
    {
        return preg_match('/^Reflection[A-Za-z]*$/', $name) === 1 || $name === 'Illuminate\\Support\\Reflector';
    }
}
