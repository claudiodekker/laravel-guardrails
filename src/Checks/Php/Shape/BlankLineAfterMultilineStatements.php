<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Shape;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Comment;
use PhpParser\Node;

/**
 * @api
 */
class BlankLineAfterMultilineStatements extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'A statement that spans several lines is followed by a blank line.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'Without a blank line, the end of a statement that spans several lines runs into the start of the next one. This flags the statement, or the comment above it, that starts on the line right after a multiline statement, in any block, class members included. One-line statements may sit together.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Add a blank line after the statement that spans several lines.';
    }

    /**
     * Get a violation at each statement, or the comment above it, that follows a multiline statement without a blank line, judged by both.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $blocks = [$file->statements()];

        foreach ($file->find(fn (Node $node) => true) as $node) {
            if ($this->holdsStatements($node)) {
                $blocks[] = $node->stmts;
            }
        }

        $violations = [];

        foreach ($blocks as $statements) {
            $statements = array_values($statements);

            for ($index = 1; $index < count($statements); $index++) {
                if ($this->isCrowded($statements[$index - 1], $statements[$index])) {
                    $violations[] = Violation::between($statements[$index - 1], $this->first($statements[$index]));
                }
            }
        }

        return $violations;
    }

    /**
     * Determine if the node holds a block of statements.
     *
     * @phpstan-assert-if-true object{stmts: array<Node>} $node
     */
    protected function holdsStatements(Node $node): bool
    {
        return property_exists($node, 'stmts') && is_array($node->stmts);
    }

    /**
     * Determine if the statement spans lines and the next one starts on the line after it.
     */
    protected function isCrowded(Node $statement, Node $next): bool
    {
        return $statement->getEndLine() > $statement->getStartLine()
            && $this->first($next)->getStartLine() === $statement->getEndLine() + 1;
    }

    /**
     * Get what the statement starts with: the first comment above it, or the statement itself.
     */
    protected function first(Node $statement): Node|Comment
    {
        return $statement->getComments()[0] ?? $statement;
    }
}
