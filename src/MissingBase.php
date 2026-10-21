<?php

namespace ClaudioDekker\LaravelGuardrails;

use RuntimeException;

/**
 * @internal
 */
class MissingBase extends RuntimeException
{
    /**
     * Create the error of a base that cannot be compared against, with whether the ref itself does not exist.
     */
    public function __construct(string $message, public readonly bool $refMissing = false)
    {
        parent::__construct($message);
    }
}
