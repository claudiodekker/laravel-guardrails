<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Safety;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\String_;

/**
 * @api
 */
class NoUnserialize extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No unserialize() without allowed_classes.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'unserialize() on data a user can influence lets them build arbitrary objects, and with them run code through magic methods like __wakeup or __destruct. This flags every call, except one that passes an options array with allowed_classes. Store data as JSON instead, or limit the classes it may create, for example unserialize($data, [\'allowed_classes\' => false]).';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Use JSON instead of unserialize(), or pass allowed_classes to name the classes it may create.';
    }

    /**
     * Get a violation at each call to unserialize() without the allowed classes named.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return array_map(Violation::at(...), $file->find(fn (Node $node) => $this->isUnguardedUnserialize($node)));
    }

    /**
     * Determine if the node calls unserialize() without an allowed_classes option.
     */
    protected function isUnguardedUnserialize(Node $node): bool
    {
        if (
            ! $node instanceof FuncCall
            || ! $node->name instanceof Node\Name
            || $node->name->toLowerString() !== 'unserialize'
        ) {
            return false;
        }

        foreach ($node->args as $argument) {
            if (
                $argument instanceof Node\Arg
                && $argument->value instanceof Array_
                && $this->namesAllowedClasses($argument->value)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the options array holds an allowed_classes key.
     */
    protected function namesAllowedClasses(Array_ $options): bool
    {
        foreach ($options->items as $item) {
            if ($item->key instanceof String_ && $item->key->value === 'allowed_classes') {
                return true;
            }
        }

        return false;
    }
}
