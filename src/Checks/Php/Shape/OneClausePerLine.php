<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Shape;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Token;

/**
 * @api
 */
class OneClausePerLine extends Check
{
    /**
     * The operators that join two clauses of a condition.
     */
    protected const OPERATORS = [
        BinaryOp\BooleanAnd::class,
        BinaryOp\BooleanOr::class,
        BinaryOp\LogicalAnd::class,
        BinaryOp\LogicalOr::class,
    ];

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'A condition of more than two clauses puts each clause on its own line, operator first.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A condition of three or more clauses on one line is hard to read, and a change to one clause rewrites the whole line in a diff. This flags each clause of such a condition that does not start its own line with its && or || first, the first clause after an opening parenthesis included. Two clauses may share a line, and a group in parentheses counts as one clause, though a group of three or more is checked on its own.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Put each clause on its own line, starting with its operator.';
    }

    /**
     * Get a violation at each clause that is not on its own line.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $tokens = $file->tokens();

        /** @var list<BinaryOp> $chains */
        $chains = $file->find(fn (Node $node) => in_array($node::class, static::OPERATORS, true));
        $inner = [];

        foreach ($chains as $chain) {
            foreach ([$chain->left, $chain->right] as $side) {
                if ($this->continuesChain($side, $tokens)) {
                    $inner[spl_object_id($side)] = true;
                }
            }
        }

        $violations = [];

        foreach ($chains as $chain) {
            $clauses = isset($inner[spl_object_id($chain)]) ? [] : $this->clauses($chain, $tokens);

            if (count($clauses) > 2) {
                array_push($violations, ...array_map(Violation::line(...), $this->misplacedLines($clauses, $tokens)));
            }
        }

        return $violations;
    }

    /**
     * Determine if the node is a chain that continues the chain around it, because no parentheses wrap it.
     *
     * @param  array<Token>  $tokens
     */
    protected function continuesChain(Node $node, array $tokens): bool
    {
        return in_array($node::class, static::OPERATORS, true) && ! $this->isGrouped($node, $tokens);
    }

    /**
     * Get the clauses of the chain, looking through the sides that continue it.
     *
     * @param  array<Token>  $tokens
     * @return list<Expr>
     */
    protected function clauses(BinaryOp $chain, array $tokens): array
    {
        $clauses = [];

        foreach ([$chain->left, $chain->right] as $side) {
            if ($side instanceof BinaryOp && $this->continuesChain($side, $tokens)) {
                array_push($clauses, ...$this->clauses($side, $tokens));
            } else {
                $clauses[] = $side;
            }
        }

        return $clauses;
    }

    /**
     * Get the lines of the clauses that do not start their own line, operator first.
     *
     * @param  list<Expr>  $clauses
     * @param  array<Token>  $tokens
     * @return list<int>
     */
    protected function misplacedLines(array $clauses, array $tokens): array
    {
        $lines = [];

        foreach ($clauses as $index => $clause) {
            if (! $this->isPlaced($clause, $index, $tokens)) {
                $lines[] = $tokens[$this->firstToken($clause, $tokens)]->line;
            }
        }

        return $lines;
    }

    /**
     * Determine if the clause starts its own line, with its operator first.
     *
     * @param  array<Token>  $tokens
     */
    protected function isPlaced(Expr $clause, int $index, array $tokens): bool
    {
        $first = $this->firstToken($clause, $tokens);

        if ($index > 0) {
            return $this->startsLine($this->previous($tokens, $first), $tokens);
        }

        return $tokens[$this->previous($tokens, $first)]->text !== '('
            || $this->startsLine($first, $tokens);
    }

    /**
     * Get the position of the first token of the clause, which is its opening parenthesis when it has one.
     *
     * @param  array<Token>  $tokens
     */
    protected function firstToken(Expr $clause, array $tokens): int
    {
        $start = $clause->getStartTokenPos();

        return $this->isGrouped($clause, $tokens) ? $this->previous($tokens, $start) : $start;
    }

    /**
     * Determine if the node is wrapped in parentheses of its own.
     *
     * @param  array<Token>  $tokens
     */
    protected function isGrouped(Node $node, array $tokens): bool
    {
        $after = $this->next($tokens, $node->getEndTokenPos());

        return $tokens[$this->previous($tokens, $node->getStartTokenPos())]->text === '('
            && ($tokens[$after]->text ?? '') === ')';
    }

    /**
     * Determine if the token at the position is the first on its line.
     *
     * @param  array<Token>  $tokens
     */
    protected function startsLine(int $position, array $tokens): bool
    {
        return $position > 0
            && $tokens[$position - 1]->id === T_WHITESPACE
            && str_contains($tokens[$position - 1]->text, "\n");
    }

    /**
     * Get the position of the token before the position, skipping whitespace and comments.
     *
     * @param  array<Token>  $tokens
     */
    protected function previous(array $tokens, int $position): int
    {
        do {
            $position--;
        } while ($position >= 0 && $this->isTrivia($tokens[$position]));

        return $position;
    }

    /**
     * Get the position of the token after the position, skipping whitespace and comments.
     *
     * @param  array<Token>  $tokens
     */
    protected function next(array $tokens, int $position): int
    {
        do {
            $position++;
        } while (isset($tokens[$position]) && $this->isTrivia($tokens[$position]));

        return $position;
    }

    /**
     * Determine if the token is whitespace or a comment.
     */
    protected function isTrivia(Token $token): bool
    {
        return in_array($token->id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }
}
