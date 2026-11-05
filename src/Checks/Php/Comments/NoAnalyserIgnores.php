<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Comments;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;

/**
 * @api
 */
class NoAnalyserIgnores extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No @phpstan-ignore or @psalm-suppress comment.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'An ignore comment hides an error from the static analyser instead of fixing it, so the mistake stays in the code and a new one on that line goes unreported. This flags every comment line with @phpstan-ignore, in any of its forms, or @psalm-suppress. It complements PhpstanIgnoresNameIdentifier, which asks an ignore to name its error, so both may flag one line. Fix the type or the code the analyser points at.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Fix the error the analyser reports instead of silencing it, by correcting the type or the code it points at.';
    }

    /**
     * Get a violation at each comment line that ignores or suppresses an analyser error.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $violations = [];

        foreach ($file->tokens() as $token) {
            if (! $token->isIgnorable() || $token->id === T_WHITESPACE) {
                continue;
            }

            foreach (explode("\n", $token->text) as $offset => $line) {
                if (preg_match('/@(?:phpstan-ignore|psalm-suppress)\b/', $line) === 1) {
                    $violations[] = Violation::line($token->line + $offset);
                }
            }
        }

        return $violations;
    }
}
