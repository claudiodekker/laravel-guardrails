<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Comments;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;

/**
 * @api
 */
class PhpstanIgnoresNameIdentifier extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'Every @phpstan-ignore names the error identifier it ignores.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'An ignore that names no error identifier hides every error on its line, so a new mistake there goes unreported. This flags a @phpstan-ignore without an identifier, and every @phpstan-ignore-line and @phpstan-ignore-next-line, which cannot name one, in any kind of comment. Write // @phpstan-ignore argument.type instead, and other errors on that line still show.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Name the error identifier, as in @phpstan-ignore argument.type, so other errors on that line still show.';
    }

    /**
     * Get a violation at each comment line that ignores PHPStan errors without an identifier.
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
                if ($this->ignoresWithoutIdentifier($line)) {
                    $violations[] = Violation::line($token->line + $offset);
                }
            }
        }

        return $violations;
    }

    /**
     * Determine if the comment line ignores PHPStan errors without naming one, which the line and next-line forms of the tag cannot.
     */
    protected function ignoresWithoutIdentifier(string $line): bool
    {
        if (preg_match('/@phpstan-ignore(-next)?-line\b/', $line) === 1) {
            return true;
        }

        return preg_match('/@phpstan-ignore(?!-)\s*(\*\/)?\s*$/', $line) === 1;
    }
}
