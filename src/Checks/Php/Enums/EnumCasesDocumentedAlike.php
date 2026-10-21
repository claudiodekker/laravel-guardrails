<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Enums;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Enums\Concerns\ChecksEnumCases;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\Stmt;

/**
 * @api
 */
class EnumCasesDocumentedAlike extends Check
{
    use ChecksEnumCases;

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'An enum documents every case or none.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'When an enum documents some cases and not others, a reader cannot tell if the bare ones need no words or were forgotten. This flags the undocumented cases of an enum that documents at least one of its cases. Document every case, or remove the docblocks so none has one.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Document every case of the enum, or none.';
    }

    /**
     * Get a violation at each undocumented case of an enum that documents some of its cases.
     *
     * @param  list<Stmt\EnumCase>  $cases
     * @return list<Violation>
     */
    protected function offending(array $cases): array
    {
        $undocumented = array_values(array_filter($cases, fn (Stmt\EnumCase $case) => $case->getDocComment() === null));

        if (count($undocumented) === count($cases)) {
            return [];
        }

        return array_map(Violation::at(...), $undocumented);
    }
}
