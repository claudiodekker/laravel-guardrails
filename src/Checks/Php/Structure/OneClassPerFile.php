<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Structure;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\Stmt\ClassLike;

/**
 * @api
 */
class OneClassPerFile extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'One named class, interface, trait or enum per file.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'Composer\'s PSR-4 autoloader looks for a class only in the file named after it, so a second class in that file loads only after something has loaded the first. This flags every named class, interface, trait or enum after the first in a file. Anonymous classes, such as the one a migration returns, are left alone, and so are the helper classes a *Test.php file in the tests holds for its tests.';
    }

    /**
     * Get the one-line description of the files the check reads.
     */
    public function scope(): string
    {
        return 'PHP except *Test.php files in the tests';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Move the class into its own file, named after it.';
    }

    /**
     * Determine if the check reads the file, which is any PHP file but a test case.
     */
    protected function reads(File $file): bool
    {
        return parent::reads($file) && ! ($file->kind === Kind::TESTS && fnmatch('*Test.php', basename($file->path)));
    }

    /**
     * Get a violation at the name of each named type after the first in the file.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $declarations = array_filter($file->findInstanceOf(ClassLike::class), fn (ClassLike $node) => $node->name !== null);

        return array_map(fn (ClassLike $node) => Violation::at($node->name ?? $node), array_slice(array_values($declarations), 1));
    }
}
