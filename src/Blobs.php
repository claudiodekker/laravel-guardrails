<?php

namespace ClaudioDekker\LaravelGuardrails;

use RuntimeException;

/**
 * @internal
 */
class Blobs
{
    /**
     * Create a reader of the blobs a stream holds, from the offset and size of each blob's contents in it, by the blob's id.
     *
     * @param  resource  $stream
     * @param  array<string, array{int, int}>  $positions
     */
    protected function __construct(protected $stream, protected array $positions)
    {
        //
    }

    /**
     * Get a reader of the given blobs, which one git process writes into a temporary file, so each is read only when asked for.
     *
     * @param  list<string>  $blobs
     *
     * @throws RuntimeException
     */
    public static function of(Git $git, array $blobs): self
    {
        $blobs = array_values(array_unique($blobs));
        $stream = $git->streamWithInput(implode('', array_map(fn (string $blob) => "{$blob}\n", $blobs)), 'cat-file', '--batch');
        $positions = [];

        foreach ($blobs as $blob) {
            $header = explode(' ', rtrim((string) fgets($stream)));

            if (($header[1] ?? null) !== 'blob') {
                throw new RuntimeException("git has no blob {$blob}.");
            }

            $size = (int) $header[2];
            $positions[$blob] = [(int) ftell($stream), $size];

            fseek($stream, $size + 1, SEEK_CUR);
        }

        return new self($stream, $positions);
    }

    /**
     * Get the start of the blob, as many bytes as it takes to tell binary data from text.
     */
    public function head(string $blob): string
    {
        [$offset, $size] = $this->positions[$blob];

        return (string) stream_get_contents($this->stream, min($size, File::BINARY_PROBE_BYTES), $offset);
    }

    /**
     * Get the whole contents of the blob.
     */
    public function read(string $blob): string
    {
        [$offset, $size] = $this->positions[$blob];

        return (string) stream_get_contents($this->stream, $size, $offset);
    }
}
