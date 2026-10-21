<?php

namespace ClaudioDekker\LaravelGuardrails\Tests\Support;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;

class EveryLine extends Check
{
    /**
     * The kinds of code the check reads.
     *
     * @var list<Kind>
     */
    protected array $kinds;

    /**
     * Create a check that flags every line of the files of the given kinds whose name matches one of the patterns.
     *
     * @param  list<Kind>|null  $kinds
     * @param  list<string>  $files
     */
    public function __construct(?array $kinds = null, protected array $files = ['*'])
    {
        $this->kinds = $kinds ?? Kind::cases();
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return $this->kinds;
    }

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'Every line breaks the rule.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Remove the line.';
    }

    /**
     * Get the patterns of the names of the files the check reads.
     *
     * @return list<string>
     */
    protected function files(): array
    {
        return $this->files;
    }

    /**
     * Get a violation at every line of the file.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return array_map(Violation::line(...), range(1, count(explode("\n", rtrim($file->contents, "\n")))));
    }
}
