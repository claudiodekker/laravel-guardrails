<?php

namespace ClaudioDekker\LaravelGuardrails\Tests\Support;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use RuntimeException;

class Crashing extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'The check crashes on every Markdown file.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Nothing to fix.';
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
     * Throw instead of reading the file.
     *
     * @return list<Violation>
     *
     * @throws RuntimeException
     */
    protected function violations(File $file): array
    {
        throw new RuntimeException('The disk is full.');
    }
}
