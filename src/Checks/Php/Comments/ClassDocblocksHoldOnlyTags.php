<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Comments;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt\ClassLike;

/**
 * @api
 */
class ClassDocblocksHoldOnlyTags extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'A class docblock holds only tags.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A class docblock that describes the class repeats its name and drifts out of date, so it holds only the tags that tell the analyser or the IDE something, such as @extends, @mixin, @property or @phpstan-type. This flags the docblock of a class, interface, trait or enum with a name when any line of it is prose, beside tags or alone. A tag may wrap onto indented lines, or onto a line that closes a bracket. A class that needs a note that fits nowhere else is listed with allowing(), along with the reason. A *Test.php file in the tests is left to NoDocblocksInTests.';
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
        return 'Keep only tags in a class docblock, such as @extends or @mixin. Delete the prose, or fold it into the class name.';
    }

    /**
     * Determine if the check reads the file, which is any PHP file but a test case.
     */
    protected function reads(File $file): bool
    {
        return parent::reads($file) && ! ($file->kind === Kind::TESTS && fnmatch('*Test.php', basename($file->path)));
    }

    /**
     * Get a violation over the lines of each named class, interface, trait or enum docblock that holds anything but tags.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $violations = [];

        foreach ($file->findInstanceOf(ClassLike::class) as $declaration) {
            $docblock = $declaration->name === null ? null : $declaration->getDocComment();

            if ($docblock !== null && ! $this->holdsOnlyTags($docblock)) {
                $violations[] = Violation::lines($docblock->getStartLine(), $docblock->getEndLine());
            }
        }

        return $violations;
    }

    /**
     * Determine if every line of the docblock is a tag, or the continuation of the tag above it, which is indented or closes a bracket.
     */
    protected function holdsOnlyTags(Doc $docblock): bool
    {
        $body = (string) preg_replace(['#^/\*\*#', '#\*/$#'], '', $docblock->getText());
        $continues = false;

        foreach (explode("\n", $body) as $line) {
            $text = (string) preg_replace('/^\s*\*?/', '', rtrim($line));

            if (trim($text) === '') {
                $continues = false;

                continue;
            }

            if (str_starts_with(ltrim($text), '@') && ! str_starts_with($text, '  ')) {
                $continues = true;
            } elseif (! ($continues && preg_match('/^(  |\s*[\]})>])/', $text) === 1)) {
                return false;
            }
        }

        return true;
    }
}
