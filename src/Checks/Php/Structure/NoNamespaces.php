<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Structure;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\Name;

/**
 * @api
 */
class NoNamespaces extends Check
{
    /**
     * The number of namespaces the description names before counting the rest.
     */
    protected const NAMED = 3;

    /**
     * The namespaces whose names the check forbids, without the backslashes around them.
     *
     * @var list<string>
     */
    protected array $namespaces;

    /**
     * Create the check with the namespaces whose names it forbids, and the failure message when it should name another fix.
     *
     * @param  list<string>  $namespaces
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $namespaces, protected ?string $message = null)
    {
        if ($namespaces === []) {
            throw new InvalidArgumentException("NoNamespaces forbids nothing. List a namespace, as new NoNamespaces(['Acme\\Billing']).");
        }

        $this->namespaces = array_map(fn (string $namespace) => trim($namespace, '\\'), $namespaces);
    }

    /**
     * Get the one-line description of the rule, naming the namespaces it forbids.
     */
    public function description(): string
    {
        $named = array_slice($this->namespaces, 0, static::NAMED);
        $rest = count($this->namespaces) - count($named);

        if ($rest > 0) {
            return 'No name from '.implode(', ', $named)." and {$rest} more.";
        }

        return 'No name from '.$this->sentence($named).'.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return "Some code may not depend on another part of the project, such as a package on its sibling. This flags every name from the listed namespaces, however it is used: an import, new, a type hint, extends, a static call, ::class or an attribute. A namespace covers the names under it, so Acme\\Billing leaves Acme\\BillingExtra alone. Remove the dependency, or move the shared code where both parts may use it. It takes the namespaces as in new NoNamespaces(['Acme\\Billing']), and message: replaces the default message.";
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return $this->message ?? 'Remove the dependency on '.$this->sentence($this->namespaces).', which this code may not use.';
    }

    /**
     * Get a violation at each name from a forbidden namespace.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return array_map(Violation::at(...), $file->find(fn (Node $node) => $node instanceof Name && $this->isForbidden($node)));
    }

    /**
     * Determine if the name is a forbidden namespace or sits under one.
     */
    protected function isForbidden(Name $name): bool
    {
        $name = $name->toLowerString();

        foreach ($this->namespaces as $namespace) {
            $namespace = strtolower($namespace);

            if ($name === $namespace || str_starts_with($name, $namespace.'\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Join the namespaces into a sentence, as "a, b or c".
     *
     * @param  list<string>  $namespaces
     */
    protected function sentence(array $namespaces): string
    {
        $last = array_pop($namespaces);

        return $namespaces === [] ? (string) $last : implode(', ', $namespaces).' or '.$last;
    }
}
