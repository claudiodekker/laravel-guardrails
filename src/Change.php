<?php

namespace ClaudioDekker\LaravelGuardrails;

/**
 * @api
 *
 * @phpstan-consistent-constructor
 */
class Change
{
    /**
     * Create the change from the file before it, or none when the change adds the file, to the file it leaves, or none when it deletes the file, with the lines it added or changed.
     *
     * @param  array<int, true>  $lines
     */
    protected function __construct(
        public readonly ?File $file,
        public readonly ?File $previous,
        protected readonly array $lines,
    ) {
        //
    }

    /**
     * Create the change that adds the file, which brings in every violation it holds. A run over every tracked file adds each one.
     *
     * @internal
     */
    public static function added(File $file): static
    {
        return new static($file, null, []);
    }

    /**
     * Create the change from the previous file, at the same path or the old path of a rename, that added or changed the given lines, leaving out any line the file does not have.
     *
     * @internal
     *
     * @param  list<int>  $lines
     */
    public static function from(File $previous, File $file, array $lines): static
    {
        $within = array_filter($lines, fn (int $line) => $line >= 1 && $line <= $file->lineCount());

        return new static($file, $previous, array_fill_keys($within, true));
    }

    /**
     * Create the change that deletes the file.
     *
     * @internal
     */
    public static function deleted(File $previous): static
    {
        return new static(null, $previous, []);
    }

    /**
     * Determine if the change adds the file, which the revision before it did not hold.
     *
     * @phpstan-assert-if-true null $this->previous
     * @phpstan-assert-if-true !null $this->file
     *
     * @phpstan-assert-if-false !null $this->previous
     */
    public function isAdded(): bool
    {
        return $this->previous === null;
    }

    /**
     * Determine if the change deletes the file.
     *
     * @phpstan-assert-if-true null $this->file
     * @phpstan-assert-if-true !null $this->previous
     *
     * @phpstan-assert-if-false !null $this->file
     */
    public function isDeleted(): bool
    {
        return $this->file === null;
    }

    /**
     * Determine if the change moves the file to another path, whether or not it also edits it.
     *
     * @phpstan-assert-if-true !null $this->file
     * @phpstan-assert-if-true !null $this->previous
     */
    public function isMoved(): bool
    {
        return $this->file !== null
            && $this->previous !== null
            && $this->file->path !== $this->previous->path;
    }

    /**
     * Determine if the change edits the file's contents, whether or not it also moves it.
     *
     * @phpstan-assert-if-true !null $this->file
     * @phpstan-assert-if-true !null $this->previous
     */
    public function isModified(): bool
    {
        return $this->file !== null
            && $this->previous !== null
            && $this->file->contents !== $this->previous->contents;
    }

    /**
     * Get the path the change leaves the file at, or the path of the file it deletes.
     */
    public function path(): string
    {
        return $this->isDeleted() ? $this->previous->path : $this->file->path;
    }

    /**
     * Determine if the change added or changed a line the violation is judged by, which a whole-file violation has none of.
     *
     * @internal
     */
    public function touches(Violation $violation): bool
    {
        return $violation->touches($this->lines);
    }
}
