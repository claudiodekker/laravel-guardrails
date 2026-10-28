<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Shape;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Switch_;

/**
 * @api
 */
class NoMatchOnCall extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No match or switch on an object built with new or a call wrapped in parentheses, such as match ((new Foo)->bar()). The subject may be a variable, a call, or a chain of calls.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A subject built inline hides the value every arm compares against. Naming it first, as in $role = (new Foo)->bar() and then match ($role), shows that value and lets you inspect it while debugging. The subject may be a variable, a call, or a chain of calls such as $user->team()->role(). This flags a subject that builds an object with new, such as (new Foo)->bar(), or that calls on a call wrapped in its own parentheses, such as ($user->team())->role().';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Name the value built with new or wrapped in parentheses in a variable, then match or switch on the variable.';
    }

    /**
     * Get a violation at the subject of each match or switch built inline.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $subjects = [
            ...array_map(fn (Match_ $match) => $match->cond, $file->findInstanceOf(Match_::class)),
            ...array_map(fn (Switch_ $switch) => $switch->cond, $file->findInstanceOf(Switch_::class)),
        ];

        $builtInline = array_filter($subjects, fn (Expr $subject) => $this->isBuiltInline($subject, $file));

        return array_map(Violation::at(...), array_values($builtInline));
    }

    /**
     * Determine if the subject, or a receiver it calls on, is a new object or a call wrapped in parentheses.
     */
    protected function isBuiltInline(Expr $subject, File $file): bool
    {
        $step = $subject;

        while ($step !== null) {
            if ($step instanceof New_) {
                return true;
            }

            $receiver = $this->receiverOf($step);

            if ($receiver instanceof CallLike && $this->isParenthesized($receiver, $file)) {
                return true;
            }

            $step = $receiver;
        }

        return false;
    }

    /**
     * Get the expression a call is made on.
     */
    protected function receiverOf(Node $call): ?Node
    {
        return match (true) {
            $call instanceof MethodCall, $call instanceof NullsafeMethodCall => $call->var,
            $call instanceof StaticCall => $call->class,
            $call instanceof FuncCall => $call->name,
            default => null,
        };
    }

    /**
     * Determine if the source wraps the expression in its own parentheses.
     */
    protected function isParenthesized(Node $expression, File $file): bool
    {
        $before = rtrim(substr($file->contents, 0, $expression->getStartFilePos()));
        $after = ltrim(substr($file->contents, $expression->getEndFilePos() + 1));

        return str_ends_with($before, '(') && str_starts_with($after, ')');
    }
}
