<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Safety;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\Expr\Eval_;

/**
 * @api
 */
class NoEval extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No eval() anywhere.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'eval() runs any string as PHP, so when part of that string comes from a user, the user can run code on the server. This flags every eval() in any PHP file, tests included. Write the code it would run directly, or choose from a fixed set of functions or classes instead.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Replace eval() with code that does the same, because it runs whatever string it is given.';
    }

    /**
     * Get a violation at each call to eval().
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return array_map(Violation::at(...), $file->findInstanceOf(Eval_::class));
    }
}
