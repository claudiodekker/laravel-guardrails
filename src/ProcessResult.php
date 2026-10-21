<?php

namespace ClaudioDekker\LaravelGuardrails;

use RuntimeException;

/**
 * @internal
 */
class ProcessResult
{
    /**
     * Create a result from the process's exit code and streams.
     *
     * @param  array<string>  $command
     */
    public function __construct(
        public readonly array $command,
        public readonly int $exitCode,
        public readonly string $output,
        public readonly string $errorOutput,
    ) {
        //
    }

    /**
     * Determine if the process exited with zero.
     */
    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * Throw an exception if the process failed.
     *
     * @throws RuntimeException
     */
    public function throw(): static
    {
        if ($this->successful()) {
            return $this;
        }

        throw new RuntimeException(implode(' ', $this->command).' failed with exit code '.$this->exitCode.': '.trim($this->errorOutput));
    }
}
