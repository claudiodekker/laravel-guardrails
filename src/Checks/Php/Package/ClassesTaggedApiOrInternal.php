<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Package;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Package\Concerns\CoversFolders;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\Stmt\ClassLike;

/**
 * @api
 */
class ClassesTaggedApiOrInternal extends Check
{
    use CoversFolders;

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'Every class is tagged @api or @internal.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A package that leaves its classes untagged leaves its users to guess which ones they may build on, so every change risks breaking one of them. This flags each class, interface, trait or enum with a name whose docblock has neither an @api nor an @internal tag, including one with no docblock at all. It takes the folders it covers, src by default, as in new ClassesTaggedApiOrInternal([\'packages/*/src\']), so an app can point it at a folder it treats as a package, such as app/Billing.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Tag the class @api when other code may build on it, or @internal when it may change without notice.';
    }

    /**
     * Get a violation at the name of each named class, interface, trait or enum whose docblock tags it neither @api nor @internal.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $violations = [];

        foreach ($file->findInstanceOf(ClassLike::class) as $declaration) {
            if ($declaration->name !== null && ! $this->isTagged($declaration)) {
                $violations[] = Violation::at($declaration->name);
            }
        }

        return $violations;
    }

    /**
     * Determine if the docblock of the declaration has an @api or @internal tag.
     */
    protected function isTagged(ClassLike $declaration): bool
    {
        $docblock = $declaration->getDocComment()?->getText() ?? '';

        return preg_match('/^\s*(\/\*\*|\*)?\s*@(api|internal)\b/m', $docblock) === 1;
    }
}
