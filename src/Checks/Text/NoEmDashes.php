<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Text;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;

/**
 * @api
 */
class NoEmDashes extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No em dash in any file.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'Em dashes are a common mark of AI-generated prose, and agents pick up the style of the files they read. This flags every line that holds an em dash, in code, docs and config alike. Hyphens, double hyphens and en dashes pass. Use a comma, a colon, parentheses or a new sentence instead.';
    }

    /**
     * Get the patterns of the names of the files the check reads, which are every file.
     *
     * @return list<string>
     */
    protected function files(): array
    {
        return ['*'];
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Replace the em dash with a comma, a colon, parentheses or a new sentence.';
    }

    /**
     * Get a violation at each line that holds an em dash.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $violations = [];

        foreach (explode("\n", $file->contents) as $index => $line) {
            if (str_contains($line, "\u{2014}")) {
                $violations[] = Violation::line($index + 1);
            }
        }

        return $violations;
    }
}
