<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Comments;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Token;

/**
 * @api
 */
class SingleCaveatDocblocks extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'A docblock holds at most one paragraph between its summary and its tags.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'Every paragraph of a docblock is prose to keep true as the code changes, and a long docblock often explains what a better name could say. This flags a docblock with a second paragraph between its summary and its tags. One caveat paragraph of any length is fine, and tags such as @param and @return do not count.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Keep one caveat paragraph after the summary. Fold the rest into it, or move it into a name.';
    }

    /**
     * Get a violation at the first line of every docblock's second caveat paragraph.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $violations = [];

        foreach ($file->tokens() as $token) {
            $line = $token->id === T_DOC_COMMENT ? $this->secondCaveat($token) : null;

            if ($line !== null) {
                $violations[] = Violation::line($line);
            }
        }

        return $violations;
    }

    /**
     * Get the first line of the docblock's second paragraph after its summary, before its tags, if it has one.
     */
    protected function secondCaveat(Token $token): ?int
    {
        $paragraphs = [];
        $inParagraph = false;

        foreach (explode("\n", $token->text) as $offset => $line) {
            $text = (string) preg_replace('/^\s*(\/\*\*|\*\/|\*)\s?/', '', $line);

            if (str_starts_with($text, '@')) {
                break;
            }

            if (trim($text) === '') {
                $inParagraph = false;

                continue;
            }

            if (! $inParagraph) {
                $paragraphs[] = $token->line + $offset;
                $inParagraph = true;
            }
        }

        return $paragraphs[2] ?? null;
    }
}
