<?php

namespace ClaudioDekker\LaravelGuardrails;

use PhpParser\Comment;
use PhpParser\Node;

/**
 * @internal
 */
class Header
{
    /**
     * Create the header above the statement: the comments a blank line sets apart from what follows.
     */
    public function __construct(protected Node $statement, protected string $source)
    {
        //
    }

    /**
     * Get the header as written, with the whitespace after it, or an empty string when the statement has none.
     */
    public function text(): string
    {
        $comments = $this->comments();

        if ($comments === []) {
            return '';
        }

        $from = $comments[0]->getStartFilePos();

        return substr($this->source, $from, $this->end() - $from);
    }

    /**
     * Get the offset where the statement starts below its header, with the comments that stay attached to it.
     */
    public function end(): int
    {
        $attached = array_slice($this->statement->getComments(), count($this->comments()));

        return $attached === [] ? $this->statement->getStartFilePos() : $attached[0]->getStartFilePos();
    }

    /**
     * Get the comments up to the last one that has a blank line below it.
     *
     * @return list<Comment>
     */
    protected function comments(): array
    {
        $comments = $this->statement->getComments();
        $header = [];

        foreach ($comments as $index => $comment) {
            $next = $comments[$index + 1] ?? $this->statement;

            if ($next->getStartLine() > $comment->getEndLine() + 1) {
                $header = array_slice($comments, 0, $index + 1);
            }
        }

        return array_values($header);
    }
}
