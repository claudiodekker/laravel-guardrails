<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Structure;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\ConfigurationFile;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;

/**
 * @api
 */
class NoTestCodeInApp extends Check
{
    /**
     * The namespace of a project whose composer.json maps none in autoload-dev.
     */
    protected const DEFAULT_NAMESPACES = ['Tests\\'];

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No name from a namespace autoload-dev maps used outside the tests.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'Classes under a namespace autoload-dev maps, such as Tests\\, load only in development, because composer install --no-dev leaves them out, so application code that uses one fails in production. This flags any name from those namespaces, imported or fully qualified, in every PHP file outside the tests except guardrails.php, reading the namespaces from composer.json and from the packages its path repositories hold, and Tests\\ when it maps none. Code under tests may use them freely.';
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
     * Get the one-line description of the files the check reads.
     */
    public function scope(): string
    {
        return parent::scope().' and guardrails.php';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Keep the namespaces autoload-dev maps out of application code, because they are only autoloaded in development.';
    }

    /**
     * Determine if the check reads the file, which is any PHP file but guardrails.php.
     */
    protected function reads(File $file): bool
    {
        return parent::reads($file) && $file->path !== ConfigurationFile::NAME;
    }

    /**
     * Get a violation at each name from a namespace autoload-dev maps.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $namespaces = $file->project->devNamespaces() ?: static::DEFAULT_NAMESPACES;

        return array_map(Violation::at(...), $file->find(fn (Node $node) => $node instanceof Node\Name && $this->isUnder($node->toString(), $namespaces)));
    }

    /**
     * Determine if the name sits under one of the namespaces.
     *
     * @param  list<string>  $namespaces
     */
    protected function isUnder(string $name, array $namespaces): bool
    {
        foreach ($namespaces as $namespace) {
            if (str_starts_with($name, $namespace)) {
                return true;
            }
        }

        return false;
    }
}
