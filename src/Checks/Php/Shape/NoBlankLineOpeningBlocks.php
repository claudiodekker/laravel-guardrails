<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Shape;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Token;

/**
 * @api
 */
class NoBlankLineOpeningBlocks extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No blank line right after the opening brace of a block.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A blank line right after an opening brace adds height and separates nothing. This flags the blank line after any {, also when a comment follows it. Start the block with its first statement or comment.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Remove the blank line that opens the block.';
    }

    /**
     * Get a violation at each blank line that directly follows an opening brace.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $tokens = array_values($file->tokens());
        $violations = [];

        foreach ($tokens as $index => $token) {
            if ($token->text === '{' && $this->isBlank($tokens[$index + 1] ?? null)) {
                $violations[] = Violation::line($token->line + 1);
            }
        }

        return $violations;
    }

    /**
     * Determine if the token is whitespace that holds a blank line.
     */
    protected function isBlank(?Token $token): bool
    {
        return $token?->id === T_WHITESPACE && substr_count($token->text, "\n") > 1;
    }
}
