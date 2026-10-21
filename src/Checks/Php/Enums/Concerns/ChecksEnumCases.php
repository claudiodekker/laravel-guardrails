<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Enums\Concerns;

use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * @internal
 */
trait ChecksEnumCases
{
    /**
     * Get a violation at each case of the enum that breaks the rule.
     *
     * @param  list<Stmt\EnumCase>  $cases
     * @return list<Violation>
     */
    abstract protected function offending(array $cases): array;

    /**
     * Get a violation at each enum case that breaks the rule.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $violations = [];

        foreach ($file->findInstanceOf(Stmt\Enum_::class) as $enum) {
            array_push($violations, ...$this->offending($this->cases($enum)));
        }

        return $violations;
    }

    /**
     * Get the cases of the enum.
     *
     * @return list<Stmt\EnumCase>
     */
    protected function cases(Stmt\Enum_ $enum): array
    {
        return array_values(array_filter($enum->stmts, fn (Node $statement) => $statement instanceof Stmt\EnumCase));
    }
}
