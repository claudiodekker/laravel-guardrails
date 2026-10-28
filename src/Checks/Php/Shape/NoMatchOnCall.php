<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Shape;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Match_;

/**
 * @api
 *
 * @deprecated Use NoParenthesesAroundCallReceivers and NoParenthesisedReceiverInConditions instead. This check is removed in 2.0.
 */
class NoMatchOnCall extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No match whose subject is a call, such as match ($user->role()).';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A call as the subject of a match hides the value every arm compares against. Naming it first, as in $role = $user->role() and then match ($role), shows that value and lets you inspect it while debugging. This flags a function, method, static or nullsafe call, and new, as the subject. Variables, properties, constants and true are left alone.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return "Name the call's result in a variable, then match on the variable.";
    }

    /**
     * Get a violation at the subject of each match on a call.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $matches = array_filter($file->findInstanceOf(Match_::class), fn (Match_ $match) => $match->cond instanceof CallLike);

        return array_map(fn (Match_ $match) => Violation::at($match->cond), array_values($matches));
    }
}
