<?php

namespace ClaudioDekker\LaravelGuardrails\Tests\Support;

use ClaudioDekker\LaravelGuardrails\ForbiddenCalls;

class NoContainerLookups extends ForbiddenCalls
{
    /**
     * The functions the rule forbids, by name.
     *
     * @var list<string>
     */
    protected array $functions = ['app', 'resolve'];

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Inject the service through the constructor instead.';
    }
}
