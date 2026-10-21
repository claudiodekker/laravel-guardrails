<?php

namespace ClaudioDekker\LaravelGuardrails;

use RuntimeException;

/**
 * @internal
 */
class SyntaxError extends RuntimeException
{
    /**
     * Create the error of the file that does not parse, with the line it starts at.
     */
    public function __construct(string $message, public readonly int $startLine, protected readonly File $source)
    {
        parent::__construct($message);
    }

    /**
     * Determine if the error comes from the given file, rather than from another file a check parsed.
     */
    public function isFrom(File $file): bool
    {
        return $this->source === $file;
    }
}
