<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Shape;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;

/**
 * @api
 */
class NoNewThenCall extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No method call with several arguments straight on a new object built with several arguments.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'An expression such as (new Invoice($user, $items))->send($mailer, $queue) builds an object and uses it in one step, and both argument lists blur together. This flags a method call straight on a new object only when both the constructor and the call take more than one argument, so (new Carbon)->addDay() passes. Assign the object to a variable first, then call the method on it.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Assign the new object to a variable, then call the method on it.';
    }

    /**
     * Get a violation at each call on a new object.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return array_map(Violation::at(...), $file->find(fn (Node $node) => $this->isNewThenCall($node)));
    }

    /**
     * Determine if the node is a call on a new object, with several arguments on both.
     */
    protected function isNewThenCall(Node $node): bool
    {
        return ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)
            && $node->var instanceof New_
            && count($node->var->args) > 1
            && count($node->args) > 1;
    }
}
