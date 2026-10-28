<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Shape;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\Concerns\ReadsParentheses;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;

/**
 * @api
 */
class NoParenthesesAroundCallReceivers extends Check
{
    use ReadsParentheses;

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No parentheses around a call that another call is made on, such as ($user->team())->role().';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'Parentheses around a call that is called again change nothing, because ($user->team())->role() does the same as $user->team()->role(), and they make a reader look for a reason. This flags a method call whose receiver is a function, method, static or nullsafe call wrapped in parentheses. A new object keeps its parentheses, as in (new Carbon)->addDay(), because PHP needs them before 8.4, and so does a group that changes the meaning, such as ($a ?? $b)->c().';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Drop the parentheses around the call, as in $user->team()->role().';
    }

    /**
     * Get a violation at each method call made on a call wrapped in parentheses.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return array_map(Violation::at(...), $file->find(fn (Node $node) => $this->isCallOnParenthesisedCall($node, $file)));
    }

    /**
     * Determine if the node is a method call whose receiver is a call wrapped in parentheses.
     */
    protected function isCallOnParenthesisedCall(Node $node, File $file): bool
    {
        return ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)
            && $node->var instanceof CallLike
            && ! $node->var instanceof New_
            && $this->isParenthesised($node->var, $file);
    }
}
