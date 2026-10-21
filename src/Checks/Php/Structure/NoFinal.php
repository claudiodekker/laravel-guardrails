<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Structure;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;

/**
 * @api
 */
class NoFinal extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No final class, method, constant or property.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A final class or method cannot be extended, and Mockery cannot mock it, so neither a test nor another package can replace its behaviour. This flags final on a class, method, class constant or property. Enums, which PHP makes final by itself, are left alone.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Remove final, so the class can be extended, and mocked in tests.';
    }

    /**
     * Get a violation at the line that declares each final class, method, constant or property.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $declarations = [
            ...$file->findInstanceOf(Class_::class),
            ...$file->findInstanceOf(ClassConst::class),
            ...$file->findInstanceOf(ClassMethod::class),
            ...$file->findInstanceOf(Property::class),
        ];

        $final = array_filter($declarations, fn (Class_|ClassConst|ClassMethod|Property $node) => ($node->flags & Modifiers::FINAL) !== 0);

        return array_map(fn (Class_|ClassConst|ClassMethod|Property $node) => Violation::at($this->nameOf($node)), array_values($final));
    }

    /**
     * Get the name of the declaration, or the declaration itself when it has no name.
     */
    protected function nameOf(Class_|ClassConst|ClassMethod|Property $declaration): Node
    {
        $name = match (true) {
            $declaration instanceof ClassConst => $declaration->consts[0]->name,
            $declaration instanceof Property => $declaration->props[0]->name,
            default => $declaration->name,
        };

        return $name ?? $declaration;
    }
}
