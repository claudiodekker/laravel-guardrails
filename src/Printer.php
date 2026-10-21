<?php

namespace ClaudioDekker\LaravelGuardrails;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

/**
 * @internal
 */
class Printer extends Standard
{
    /**
     * Print the items of an array one per line with a trailing comma, the way a hand-written guardrails.php lays them out.
     *
     * @param  array<Node>  $nodes
     */
    protected function pMaybeMultiline(array $nodes, bool $trailingComma = false): string
    {
        if ($nodes === []) {
            return '';
        }

        return $this->pCommaSeparatedMultiline($nodes, trailingComma: true).$this->nl;
    }
}
