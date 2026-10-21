<?php

namespace ClaudioDekker\LaravelGuardrails;

use RuntimeException;

/**
 * @internal
 */
class Process
{
    /**
     * Run the command in the given directory, feeding it the input, under the given environment or the current one, and wait for it to exit.
     *
     * @param  array<string>  $command
     * @param  array<string, string>|null  $environment
     */
    public static function run(string $directory, array $command, string $input = '', ?array $environment = null): ProcessResult
    {
        [$exitCode, $output, $errorOutput] = static::execute($directory, $command, $input, $environment);

        return new ProcessResult($command, $exitCode, (string) stream_get_contents($output), (string) stream_get_contents($errorOutput));
    }

    /**
     * Run the command as run() does and get its output as a stream from the start, which keeps output of any size out of memory, refusing a command that failed.
     *
     * @param  array<string>  $command
     * @param  array<string, string>|null  $environment
     * @return resource
     *
     * @throws RuntimeException
     */
    public static function stream(string $directory, array $command, string $input = '', ?array $environment = null)
    {
        [$exitCode, $output, $errorOutput] = static::execute($directory, $command, $input, $environment);

        $result = new ProcessResult($command, $exitCode, '', (string) stream_get_contents($errorOutput));
        $result->throw();

        return $output;
    }

    /**
     * Run the command, wait for it to exit, and get its exit code with its output and error output, each in a temporary file rewound to the start.
     *
     * @param  array<string>  $command
     * @param  array<string, string>|null  $environment
     * @return array{int, resource, resource}
     *
     * @throws RuntimeException
     */
    protected static function execute(string $directory, array $command, string $input, ?array $environment): array
    {
        $streams = [tmpfile(), tmpfile(), tmpfile()];

        if (in_array(false, $streams, true)) {
            throw new RuntimeException('Could not create the temporary files to run '.implode(' ', $command));
        }

        fwrite($streams[0], $input);
        rewind($streams[0]);

        $process = proc_open(array_values($command), $streams, $pipes, $directory, $environment);

        if ($process === false) {
            throw new RuntimeException('Could not start '.implode(' ', $command));
        }

        $exitCode = proc_close($process);

        rewind($streams[1]);
        rewind($streams[2]);

        return [$exitCode, $streams[1], $streams[2]];
    }
}
