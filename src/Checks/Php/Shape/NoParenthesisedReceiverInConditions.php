<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Shape;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\Concerns\ReadsParentheses;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\While_;

/**
 * @api
 */
class NoParenthesisedReceiverInConditions extends Check
{
    use ReadsParentheses;

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No condition or subject that calls a method on a parenthesised value, such as match ((new Foo)->bar()).';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A match, switch, if, elseif or while that opens with a doubled parenthesis, as in match ((new Foo)->bar()), builds a value and uses it in one step, so a reader cannot see the value being tested or inspect it while debugging. This flags a condition or subject that is a method call made on a value in parentheses, however long the chain, and the same when more follows it, as in if ((new Foo)->ok() && $ready). A group with nothing called on it, such as if (($a && $b) || $c), passes, and so does a call with no extra parentheses, such as match ($user->team()->role()).';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Name the parenthesised value in a variable first, then match or test the variable.';
    }

    /**
     * Get a violation at the condition or subject of each match, switch, if, elseif and while that calls a method on a parenthesised value.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $violations = [];

        foreach ($file->find(fn (Node $node) => $this->conditionOf($node) !== null) as $node) {
            $condition = $this->conditionOf($node);

            if ($condition !== null && $this->startsWithParenthesisedReceiver($condition, $file)) {
                $violations[] = Violation::at($condition);
            }
        }

        return $violations;
    }

    /**
     * Determine if the condition opens with a method call on a value in parentheses, looking down the left of its operators and the receivers of its calls.
     */
    protected function startsWithParenthesisedReceiver(Expr $condition, File $file): bool
    {
        if ($condition instanceof BinaryOp) {
            return $this->startsWithParenthesisedReceiver($condition->left, $file);
        }

        if (! $condition instanceof MethodCall && ! $condition instanceof NullsafeMethodCall) {
            return false;
        }

        return $this->isParenthesised($condition->var, $file)
            || $this->startsWithParenthesisedReceiver($condition->var, $file);
    }

    /**
     * Get the condition or subject of a match, switch, if, elseif or while, or null for any other node.
     */
    protected function conditionOf(Node $node): ?Expr
    {
        return match (true) {
            $node instanceof Match_,
            $node instanceof Switch_,
            $node instanceof If_,
            $node instanceof ElseIf_,
            $node instanceof While_ => $node->cond,
            default => null,
        };
    }
}
