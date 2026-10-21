<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Enums;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Enums\Concerns\ChecksEnumCases;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\Stmt;

/**
 * @api
 */
class SortedEnumCases extends Check
{
    use ChecksEnumCases;

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'The cases of an enum are in alphabetical order.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'Cases in alphabetical order are quick to scan, and a new case has one place to go. This flags each case whose name sorts before the case above it, compared byte by byte, so uppercase letters sort before lowercase. Constants and methods in the enum are not checked.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return "Sort the enum's cases alphabetically.";
    }

    /**
     * Get a violation at each case that sorts before the case above it, judged by both names and naming the move.
     *
     * @param  list<Stmt\EnumCase>  $cases
     * @return list<Violation>
     */
    protected function offending(array $cases): array
    {
        $offending = [];

        foreach ($cases as $index => $case) {
            $previous = $cases[$index - 1] ?? null;

            if ($previous !== null && strcmp($case->name->toString(), $previous->name->toString()) < 0) {
                $offending[] = Violation::between($previous->name, $case->name, "Move case {$case->name} above case {$previous->name}.");
            }
        }

        return $offending;
    }
}
