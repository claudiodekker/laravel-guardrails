<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\Concerns;

use ClaudioDekker\LaravelGuardrails\File;
use PhpParser\Node;
use PhpParser\Token;

/**
 * @internal
 */
trait ReadsParentheses
{
    /**
     * Determine if the node is wrapped in parentheses of its own, which the parser drops from the tree.
     */
    protected function isParenthesised(Node $node, File $file): bool
    {
        $tokens = $file->tokens();

        $before = $this->neighbour($tokens, $node->getStartTokenPos(), -1);
        $after = $this->neighbour($tokens, $node->getEndTokenPos(), 1);

        return ($tokens[$before]->text ?? null) === '('
            && ($tokens[$after]->text ?? null) === ')';
    }

    /**
     * Get the position of the nearest token on the given side that is not whitespace or a comment.
     *
     * @param  array<Token>  $tokens
     */
    protected function neighbour(array $tokens, int $position, int $step): int
    {
        do {
            $position += $step;
        } while (isset($tokens[$position]) && $tokens[$position]->isIgnorable());

        return $position;
    }
}
