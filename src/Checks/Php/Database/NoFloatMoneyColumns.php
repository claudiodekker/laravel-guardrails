<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Database;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;

/**
 * @api
 */
class NoFloatMoneyColumns extends Check
{
    /**
     * The words in a column name that mean it holds money.
     */
    protected const MONEY_WORDS = ['price', 'amount', 'total', 'cost', 'fee', 'balance'];

    /**
     * The column types that lose cents to rounding.
     */
    protected const FLOAT_TYPES = ['float', 'double'];

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No float or double column for a price, amount, total, cost, fee or balance.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A float or double column stores amounts like 0.1 as an approximation, so totals drift by a cent. This flags float() and double() in a migration when the column name contains price, amount, total, cost, fee or balance, such as $table->float(\'unit_price\'). Store whole cents in an integer column instead.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return [Kind::MIGRATIONS];
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Store money as an integer of its smallest unit, such as cents, in an integer column.';
    }

    /**
     * Get a violation at each money column given a float or double type.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return array_map(Violation::at(...), $file->find(fn (Node $node) => $this->isFloatMoneyColumn($node)));
    }

    /**
     * Determine if the node calls float() or double() with a column name that means money.
     */
    protected function isFloatMoneyColumn(Node $node): bool
    {
        if (
            ! $node instanceof MethodCall
            || ! $node->name instanceof Identifier
            || ! in_array($node->name->toLowerString(), static::FLOAT_TYPES, true)
        ) {
            return false;
        }

        $column = $node->args[0] ?? null;

        if (! $column instanceof Node\Arg || ! $column->value instanceof String_) {
            return false;
        }

        foreach (static::MONEY_WORDS as $word) {
            if (str_contains(strtolower($column->value->value), $word)) {
                return true;
            }
        }

        return false;
    }
}
