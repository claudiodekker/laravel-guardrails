<?php

namespace ClaudioDekker\LaravelGuardrails\Tests\Support;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use RuntimeException;

class Unavailable extends Check
{
    /**
     * Create the check, which cannot reach the service it needs.
     *
     * @throws RuntimeException
     */
    public function __construct()
    {
        throw new RuntimeException('The licence server is down.');
    }

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'The check needs a service that is down.';
    }

    /**
     * Get no violations, because the check never gets built.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return [];
    }
}
