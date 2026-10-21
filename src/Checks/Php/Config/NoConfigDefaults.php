<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Config;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;

/**
 * @api
 */
class NoConfigDefaults extends Check
{
    /**
     * The methods of the config repository that read a value and take a default, lowercase.
     */
    protected const READS = ['array', 'boolean', 'float', 'get', 'integer', 'string'];

    /**
     * The names the Config facade goes by.
     */
    protected const FACADES = ['Config', 'Illuminate\\Support\\Facades\\Config'];

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No inline default on a config read, such as config(\'app.limit\', 5).';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A default at the call site, such as config(\'app.limit\', 5), keeps a second copy of the value outside the config file, and the two drift apart. This flags config() with a default, and get, string, integer, float, boolean or array with a default on config() or the Config facade. Writes such as Config::set() and defaults on other objects, such as Cache::get(), are left alone. Give the key its value in the config file instead.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Remove the inline default and give the key its value in the config file, so the value lives in one place.';
    }

    /**
     * Get a violation at each config read with an inline default.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return array_map(Violation::at(...), $file->find(fn (Node $node) => $this->readsWithDefault($node)));
    }

    /**
     * Determine if the node reads a config value and passes a default.
     */
    protected function readsWithDefault(Node $node): bool
    {
        if ($node instanceof FuncCall) {
            return $this->isConfigFunction($node) && count($node->args) > 1;
        }

        if ($node instanceof MethodCall) {
            return $node->var instanceof FuncCall
                && $this->isConfigFunction($node->var)
                && $node->var->args === []
                && $this->isReadWithDefault($node);
        }

        return $node instanceof StaticCall
            && $node->class instanceof Node\Name
            && in_array($node->class->toString(), static::FACADES, true)
            && $this->isReadWithDefault($node);
    }

    /**
     * Determine if the call is to the config() function.
     */
    protected function isConfigFunction(FuncCall $call): bool
    {
        return $call->name instanceof Node\Name && $call->name->toLowerString() === 'config';
    }

    /**
     * Determine if the call reads a value and passes a default.
     */
    protected function isReadWithDefault(MethodCall|StaticCall $call): bool
    {
        return $call->name instanceof Identifier
            && in_array($call->name->toLowerString(), static::READS, true)
            && count($call->args) > 1;
    }
}
