<?php

namespace ClaudioDekker\LaravelGuardrails;

/**
 * @internal
 */
class Report
{
    /**
     * Create a report of the given failures, in the order the checks ran, over the given number of files.
     *
     * @param  list<Failure>  $failures
     */
    public function __construct(public readonly array $failures, public readonly int $files)
    {
        //
    }

    /**
     * Determine if every check passed.
     */
    public function passed(): bool
    {
        return $this->failures === [];
    }

    /**
     * Get the exit code the run ends with: 0 when every check passed, 2 when a check crashed, else 1.
     */
    public function exitCode(): int
    {
        if ($this->passed()) {
            return 0;
        }

        foreach ($this->failures as $failure) {
            if ($failure->crash) {
                return 2;
            }
        }

        return 1;
    }

    /**
     * Render the failures grouped under their labels, in the order the labels first appear, one "path:line message" per failure, or "path message" for the whole file.
     */
    public function render(): string
    {
        $groups = [];

        foreach ($this->failures as $failure) {
            $groups[$failure->label][] = $failure;
        }

        return implode("\n", array_map(fn (array $failures) => $this->renderGroup($failures), $groups));
    }

    /**
     * Render the failures of one label under it, with the rest of each distinct multi-line message once under them.
     *
     * @param  non-empty-list<Failure>  $failures
     */
    protected function renderGroup(array $failures): string
    {
        $lines = [$failures[0]->label];

        foreach ($failures as $failure) {
            $location = $this->location($failure);
            $lines[] = '  '.($location === '' ? '' : $location.' ').strtok($failure->message, "\n");
        }

        $rests = array_unique(array_map(fn (Failure $failure) => explode("\n", $failure->message, 2)[1] ?? '', $failures));

        foreach (array_filter($rests, fn (string $rest) => $rest !== '') as $rest) {
            $lines[] = $this->indent($rest);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Get where the failure is: the path for the whole file, else "path:line".
     */
    protected function location(Failure $failure): string
    {
        $line = $failure->violation->line;

        return $line === null ? $failure->path : "{$failure->path}:{$line}";
    }

    /**
     * Indent every line of the text that holds something.
     */
    protected function indent(string $text): string
    {
        $lines = array_map(fn (string $line) => $line === '' ? '' : '  '.$line, explode("\n", $text));

        return implode("\n", $lines);
    }
}
