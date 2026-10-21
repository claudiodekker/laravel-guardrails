<?php

namespace ClaudioDekker\LaravelGuardrails\Tests\Support;

class Explained extends EveryLine
{
    /**
     * Get the explanation of the rule, long enough to wrap over several lines.
     */
    public function explanation(): string
    {
        return 'Every line breaks this rule, so the check flags each line of every file it reads. '
            .'A rule this strict makes no sense in a real project, but it shows how a long explanation wraps. '
            .'The paragraph wraps to the width of the terminal and keeps its words whole. '
            .'On a terminal too short for all of it, the last line it has room for ends in an ellipsis.';
    }
}
