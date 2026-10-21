<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Config;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;

/**
 * @api
 */
class SortedConfigKeys extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'The keys of a config array are in alphabetical order.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'Keys in alphabetical order are quick to scan, and a new option has one place to go. This flags each key of a config array, at any depth, that sorts before the key above it, compared byte by byte, so uppercase letters sort before lowercase. An array that is a list, mixes keyed and unkeyed items, or has a key that is not a string literal is left alone.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return [Kind::CONFIG];
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return "Sort the array's keys alphabetically.";
    }

    /**
     * Get a violation at each key that sorts before the key above it, judged by both keys, since inserting either breaks the order.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $violations = [];

        foreach ($file->findInstanceOf(Array_::class) as $array) {
            array_push($violations, ...$this->unsorted($array));
        }

        return $violations;
    }

    /**
     * Get a violation at each key that sorts before the key above it, naming the move, or none when an item has no string literal key.
     *
     * @return list<Violation>
     */
    protected function unsorted(Array_ $array): array
    {
        $unsorted = [];
        $previous = null;

        foreach ($array->items as $item) {
            if (! $item->key instanceof String_) {
                return [];
            }

            if ($previous !== null && strcmp($item->key->value, $previous->value) < 0) {
                $unsorted[] = Violation::between($previous, $item->key, "Move '{$item->key->value}' above '{$previous->value}'.");
            }

            $previous = $item->key;
        }

        return $unsorted;
    }
}
