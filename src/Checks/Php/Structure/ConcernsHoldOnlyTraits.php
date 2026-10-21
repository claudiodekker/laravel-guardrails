<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Structure;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Trait_;

/**
 * @api
 */
class ConcernsHoldOnlyTraits extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'A Concerns folder holds only traits.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'Laravel puts traits in a folder named Concerns, so a reader who opens one expects a trait. This flags a named class, interface or enum in a Concerns folder at any depth, outside tests. Anonymous classes are left alone. Make it a trait, or move it out of the Concerns folder.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return Kind::except(Kind::TESTS);
    }

    /**
     * Get the one-line description of the files the check reads.
     */
    public function scope(): string
    {
        return 'PHP in Concerns folders outside tests';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Make it a trait, or move it out of the Concerns folder.';
    }

    /**
     * Determine if the check reads the file, which is any PHP file in a Concerns folder.
     */
    protected function reads(File $file): bool
    {
        return parent::reads($file) && $file->isIn('Concerns');
    }

    /**
     * Get a violation at the name of each named type that is not a trait.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $declarations = array_filter($file->findInstanceOf(ClassLike::class), fn (ClassLike $node) => $node->name !== null && ! $node instanceof Trait_);

        return array_map(fn (ClassLike $node) => Violation::at($node->name ?? $node), array_values($declarations));
    }
}
