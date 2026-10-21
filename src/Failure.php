<?php

namespace ClaudioDekker\LaravelGuardrails;

use Throwable;

/**
 * @internal
 *
 * @phpstan-consistent-constructor
 */
class Failure
{
    /**
     * Create a failure under the given label on the violation in the file at the path, with the message to print, and whether it records a crash of the check.
     */
    public function __construct(
        public readonly string $label,
        public readonly string $path,
        public readonly Violation $violation,
        public readonly string $message,
        public readonly bool $crash = false,
    ) {
        //
    }

    /**
     * Create the failure of a check under the given label that crashed on the file at the path, naming where it crashed from the project's folder and why.
     */
    public static function crash(string $label, string $path, Throwable $exception, string $directory): static
    {
        $reason = str_ends_with($exception->getMessage(), '.') ? $exception->getMessage() : $exception->getMessage().'.';
        $message = 'The check crashed at '.static::location($exception, $directory).": {$reason} Fix the check, or allow the file.";

        return new static($label, $path, Violation::file(), $message, crash: true);
    }

    /**
     * Create the failure that counts the files a check crashed on beyond the ones reported, which sits at no path.
     */
    public static function crashSummary(string $label, int $more): static
    {
        $files = $more === 1 ? '1 more file' : "{$more} more files";

        return new static($label, '', Violation::file(), "The check crashed on {$files} too. Fix the check.", crash: true);
    }

    /**
     * Get the file and line the exception was thrown at, with the file from the project's folder when it is inside it.
     */
    public static function location(Throwable $exception, string $directory): string
    {
        $prefix = rtrim($directory, '/').'/';
        $file = str_starts_with($exception->getFile(), $prefix) ? substr($exception->getFile(), strlen($prefix)) : $exception->getFile();

        return "{$file}:{$exception->getLine()}";
    }
}
