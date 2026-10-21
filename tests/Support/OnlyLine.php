<?php

namespace ClaudioDekker\LaravelGuardrails\Tests\Support;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;

class OnlyLine extends Check
{
    /**
     * Create a check that flags the given line of every file.
     */
    public function __construct(protected int $line)
    {
        //
    }

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return "Line {$this->line} breaks the rule.";
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return "Remove line {$this->line}.";
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
     * Get a violation at the one line of the file that breaks the rule.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return [Violation::line($this->line)];
    }
}
