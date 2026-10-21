<?php

namespace ClaudioDekker\LaravelGuardrails;

use InvalidArgumentException;
use PhpParser\Comment;
use PhpParser\Node;

/**
 * @api
 */
final class Violation
{
    /**
     * Create a violation reported at the line, when it has one, that a change to any line of the ranges brings in.
     *
     * @param  list<array{int, int}>  $ranges
     */
    protected function __construct(
        public readonly ?int $line,
        public readonly array $ranges,
        public readonly ?string $message = null,
    ) {
        //
    }

    /**
     * Create a violation of the node, reported at the line of its name when it has one, that a change to any of its lines brings in, its attributes included and the comments above it left out.
     */
    public static function at(Node $node, ?string $message = null): self
    {
        return new self(self::lineOf($node), [self::rangeOf($node)], $message);
    }

    /**
     * Create a violation of one line.
     */
    public static function line(int $line, ?string $message = null): self
    {
        return new self($line, [[$line, $line]], $message);
    }

    /**
     * Create a violation of the lines from the first to the last, reported at the first.
     *
     * @throws InvalidArgumentException
     */
    public static function lines(int $first, int $last, ?string $message = null): self
    {
        if ($last < $first) {
            throw new InvalidArgumentException("Violation::lines() takes the first line before the last, not {$first} to {$last}.");
        }

        return new self($first, [[$first, $last]], $message);
    }

    /**
     * Create a violation of the lower node or comment against the one above it, reported at the lower one, that a change to either brings in.
     */
    public static function between(Node|Comment $above, Node|Comment $below, ?string $message = null): self
    {
        return new self(self::lineOf($below), [self::rangeOf($above), self::rangeOf($below)], $message);
    }

    /**
     * Create a violation of the whole file rather than of its lines, shown at the given line when it has one.
     */
    public static function file(?string $message = null, ?int $line = null): self
    {
        return new self($line, [], $message);
    }

    /**
     * Determine if the violation is of the whole file rather than of its lines.
     */
    public function isWholeFile(): bool
    {
        return $this->ranges === [];
    }

    /**
     * Determine if any line the violation is judged by is among the given lines, which none of a whole-file violation is.
     *
     * @internal
     *
     * @param  array<int, true>  $lines
     */
    public function touches(array $lines): bool
    {
        foreach ($this->ranges as [$first, $last]) {
            foreach (array_keys($lines) as $line) {
                if ($line >= $first && $line <= $last) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the line the node or comment is reported at: the line of a node's name when the name is a node of its own, else the first line.
     */
    protected static function lineOf(Node|Comment $node): int
    {
        if ($node instanceof Comment) {
            return $node->getStartLine();
        }

        $name = property_exists($node, 'name') ? $node->name : null;

        return $name instanceof Node ? $name->getStartLine() : $node->getStartLine();
    }

    /**
     * Get the lines of the node or comment, from its first to its last.
     *
     * @return array{int, int}
     */
    protected static function rangeOf(Node|Comment $node): array
    {
        return [$node->getStartLine(), $node->getEndLine()];
    }
}
