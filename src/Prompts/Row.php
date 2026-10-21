<?php

namespace ClaudioDekker\LaravelGuardrails\Prompts;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Group;

/**
 * @internal
 */
class Row
{
    /**
     * Create a row of the tree, which stands for the group itself unless it names one of its checks.
     *
     * @param  class-string<Check>|null  $check
     */
    public function __construct(public readonly Group $group, public readonly ?string $check = null)
    {
        //
    }
}
