<?php

namespace ClaudioDekker\LaravelGuardrails\Tests\Support;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;

class Silent extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'The first line of every Markdown file breaks the rule, and no message says why.';
    }

    /**
     * Get the patterns of the names of the files the check reads, which are the Markdown files.
     *
     * @return list<string>
     */
    protected function files(): array
    {
        return ['*.md'];
    }

    /**
     * Get a violation at the first line, without a message.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return [Violation::line(1)];
    }
}
