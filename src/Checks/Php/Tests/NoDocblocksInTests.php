<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Tests;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;

/**
 * @api
 */
class NoDocblocksInTests extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No docblocks in tests, because the test\'s name says what it does.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A test\'s name already says what it checks, and a docblock that repeats it drifts out of date. This flags any docblock in a file ending in Test.php, above or inside a test. A docblock that holds only tags may stay, which are type and analyser tags, such as @var, @param, @return or @phpstan-ignore, and PHPUnit annotations, such as @test, @dataProvider, @depends, @covers, @group, @testWith or @doesNotPerformAssertions. Prose beside a tag is flagged.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return [Kind::TESTS];
    }

    /**
     * Get the patterns of the names of the files the check reads, which are the tests.
     *
     * @return list<string>
     */
    protected function files(): array
    {
        return ['*Test.php'];
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return "Remove the docblock; the test's name says what it does. Tags may stay on their own: type and analyser tags, such as @var, @param and @phpstan-*, and PHPUnit annotations, such as @test and @dataProvider.";
    }

    /**
     * Get a violation at the first line of each docblock that holds more than type, analyser and PHPUnit tags.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $violations = [];

        foreach ($file->tokens() as $token) {
            if ($token->id === T_DOC_COMMENT && ! $this->holdsOnlyTags($token->text)) {
                $violations[] = Violation::lines($token->line, $token->line + substr_count($token->text, "\n"));
            }
        }

        return $violations;
    }

    /**
     * Determine if every line of the docblock that holds something starts with a type, analyser or PHPUnit tag.
     */
    protected function holdsOnlyTags(string $docblock): bool
    {
        $body = (string) preg_replace(['#^/\*\*#', '#\*/$#'], '', $docblock);

        foreach (explode("\n", $body) as $line) {
            $line = trim(ltrim(trim($line), '*'));

            if ($line !== '' && preg_match('/^@(var|param|return|throws|template(-[a-z]+)?|phpstan-[a-z-]+|psalm-[a-z-]+|test|testWith|dataProvider|depends|covers|coversNothing|uses|group|ticket|doesNotPerformAssertions|requires|backupGlobals|runInSeparateProcess|preserveGlobalState)(\s|$)/', $line) !== 1) {
                return false;
            }
        }

        return true;
    }
}
