<?php

namespace ClaudioDekker\LaravelGuardrails\Testing;

use ClaudioDekker\LaravelGuardrails\Change;
use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Git;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Process;
use ClaudioDekker\LaravelGuardrails\Project;
use ClaudioDekker\LaravelGuardrails\Violation;
use RuntimeException;

/**
 * @api
 *
 * @phpstan-consistent-constructor
 */
class TestFile
{
    /**
     * Create a file to run a check over, from the given project, with the path, contents and kind it had before the change when the test gives them, and whether the change deletes it.
     */
    protected function __construct(
        protected readonly Kind $kind,
        protected readonly string $path,
        protected readonly string $contents,
        protected readonly Project $project,
        protected readonly ?string $previousPath = null,
        protected readonly ?string $previousContents = null,
        protected readonly ?Kind $previousKind = null,
        protected readonly bool $deleted = false,
    ) {
        //
    }

    /**
     * Create a file of the given kind at the path from the project root, holding the contents, which a check reads as if the change added it, from a project that holds no other file.
     */
    public static function of(Kind $kind, string $path, string $contents): static
    {
        return new static($kind, $path, $contents, Project::nowhere());
    }

    /**
     * Place the file in the project of every file in the given folder, tracked by git or not, whose phpunit.xml and composer.json a check may read, and whose PHP version the parser targets.
     */
    public function in(string $root): static
    {
        return new static($this->kind, $this->path, $this->contents, Project::onDisk($root), $this->previousPath, $this->previousContents, $this->previousKind, $this->deleted);
    }

    /**
     * Make the change one that turns the given contents into the file's, at the same path unless the file also moved.
     */
    public function changedFrom(string $contents): static
    {
        return new static($this->kind, $this->path, $this->contents, $this->project, $this->previousPath ?? $this->path, $contents, $this->previousKind, $this->deleted);
    }

    /**
     * Make the change one that moves the file from the given path, of the given kind or the file's own, with the same contents unless the file also changed.
     */
    public function movedFrom(string $path, ?Kind $kind = null): static
    {
        return new static($this->kind, $this->path, $this->contents, $this->project, $path, $this->previousContents ?? $this->contents, $kind, $this->deleted);
    }

    /**
     * Make the change one that deletes the file, as the test wrote it.
     */
    public function deleted(): static
    {
        return new static($this->kind, $this->path, $this->contents, $this->project, $this->previousPath, $this->previousContents, $this->previousKind, deleted: true);
    }

    /**
     * Get what the check reports for the change, as a run would: the line and the message of every violation for an added file, else of the ones the change brings in. The line is null for a violation of the whole file.
     *
     * @return list<array{line: int|null, message: string}>
     */
    public function reports(Check $check): array
    {
        return array_map(
            fn (Violation $violation) => ['line' => $violation->line, 'message' => $check->messageOf($violation)],
            $check->check($this->change()),
        );
    }

    /**
     * Get the line of each violation the check reports for the change, leaving out a violation shown at no line.
     *
     * @return list<int>
     */
    public function lines(Check $check): array
    {
        $lines = [];

        foreach ($this->reports($check) as $report) {
            if ($report['line'] !== null) {
                $lines[] = $report['line'];
            }
        }

        return $lines;
    }

    /**
     * Build the change a run would see, with the lines git's zero-context diff of the contents before and after adds or changes.
     */
    protected function change(): Change
    {
        $file = new File($this->path, $this->contents, $this->kind, $this->project);

        if ($this->deleted) {
            return Change::deleted($file);
        }

        if ($this->previousPath === null || $this->previousContents === null) {
            return Change::added($file);
        }

        $previous = new File($this->previousPath, $this->previousContents, $this->previousKind ?? $this->kind, $this->project);

        return Change::from($previous, $file, $this->changedLines($previous, $file));
    }

    /**
     * Get the lines the change adds or changes, from git's zero-context diff of the previous file against the file.
     *
     * @return list<int>
     *
     * @throws RuntimeException
     */
    protected function changedLines(File $previous, File $file): array
    {
        if ($previous->contents === $file->contents) {
            return [];
        }

        $before = (string) tempnam(sys_get_temp_dir(), 'guardrails-diff-');
        $after = (string) tempnam(sys_get_temp_dir(), 'guardrails-diff-');

        file_put_contents($before, $previous->contents);
        file_put_contents($after, $file->contents);

        try {
            $result = Process::run(sys_get_temp_dir(), ['git', 'diff', '--no-index', ...Git::DIFF_OPTIONS, $before, $after], environment: Git::environment());
        } finally {
            unlink($before);
            unlink($after);
        }

        if ($result->exitCode > 1) {
            throw new RuntimeException('git diff failed with exit code '.$result->exitCode.': '.trim($result->errorOutput));
        }

        return Git::changedLines($result->output, $file->lineCount());
    }
}
