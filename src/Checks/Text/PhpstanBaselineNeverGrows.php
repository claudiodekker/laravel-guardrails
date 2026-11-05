<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Text;

use ClaudioDekker\LaravelGuardrails\Change;
use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * @api
 */
class PhpstanBaselineNeverGrows extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'A PHPStan baseline never ignores more errors than it did.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A baseline lists the errors PHPStan ignores, so adding to it hides a new error instead of fixing it. This adds up the errors a baseline ignores, which is the count of each entry, or one for an entry without a count, and flags a change that makes the total larger, a new baseline included. A baseline that stays the same size or shrinks passes. It reads phpstan-baseline.neon and phpstan-baseline.php, and split baselines such as phpstan-baseline-level-5.neon, in the block list PHPStan writes. Fix the errors instead of baselining them.';
    }

    /**
     * Get the one-line description of the files the check reads.
     */
    public function scope(): string
    {
        return 'PHPStan baselines (phpstan-baseline*.neon and phpstan-baseline*.php)';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Fix the errors PHPStan reports instead of adding them to the baseline, which may only shrink.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return [Kind::TEXT];
    }

    /**
     * Get the patterns of the names of the files the check reads, which are the baselines PHPStan writes, in NEON or PHP, and split baselines named after them.
     *
     * @return list<string>
     */
    protected function files(): array
    {
        return ['phpstan-baseline*.neon', 'phpstan-baseline*.php'];
    }

    /**
     * Get no violations of the file alone, because the rule compares the baseline with the one before.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return [];
    }

    /**
     * Get a whole-file violation when the change makes the baseline ignore more errors than before, which a new baseline does unless it ignores none.
     *
     * @return list<Violation>
     */
    protected function violationsOf(Change $change): array
    {
        if ($change->isDeleted()) {
            return [];
        }

        $before = $change->isAdded() ? 0 : $this->ignoredErrors($change->previous);

        return $this->ignoredErrors($change->file) > $before ? [Violation::file()] : [];
    }

    /**
     * Get the number of errors the baseline ignores, which is the sum of the count of its entries.
     */
    protected function ignoredErrors(File $baseline): int
    {
        return $baseline->isPhp() ? $this->ignoredErrorsInPhp($baseline) : $this->ignoredErrorsInNeon($baseline->contents);
    }

    /**
     * Get the number of errors the PHP baseline ignores, from each $ignoreErrors[] = [...] entry.
     */
    protected function ignoredErrorsInPhp(File $baseline): int
    {
        $total = 0;

        foreach ($baseline->findInstanceOf(Assign::class) as $assignment) {
            $entry = $this->phpEntryOf($assignment);

            if ($entry !== null) {
                $total += $this->countOfPhpEntry($entry);
            }
        }

        return $total;
    }

    /**
     * Get the array of the entry an assignment adds to the baseline, as in $ignoreErrors[] = [...], or null for any other assignment.
     */
    protected function phpEntryOf(Assign $assignment): ?Array_
    {
        $isEntry = $assignment->var instanceof ArrayDimFetch
            && $assignment->var->dim === null
            && $assignment->var->var instanceof Variable
            && $assignment->var->var->name === 'ignoreErrors';

        return $isEntry && $assignment->expr instanceof Array_ ? $assignment->expr : null;
    }

    /**
     * Get the number of errors the entry ignores, which is its count, or one without a count.
     */
    protected function countOfPhpEntry(Array_ $entry): int
    {
        foreach ($entry->items as $item) {
            $isCount = $item->key instanceof String_
                && $item->key->value === 'count'
                && $item->value instanceof Int_;

            if ($isCount) {
                return $item->value->value;
            }
        }

        return 1;
    }

    /**
     * Get the number of errors the NEON baseline ignores, which is the sum of the count of its entries.
     */
    protected function ignoredErrorsInNeon(string $contents): int
    {
        return array_sum(array_map($this->countOfNeonEntry(...), $this->neonEntries($contents)));
    }

    /**
     * Get the list items under each ignoreErrors key, each as the trimmed lines of the entry with the dash taken off the first.
     *
     * @return list<list<string>>
     */
    protected function neonEntries(string $contents): array
    {
        $entries = [];
        $keyIndent = null;
        $itemIndent = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $text = trim($line);

            if ($text === '' || str_starts_with($text, '#')) {
                continue;
            }

            $indent = strlen($line) - strlen(ltrim($line));
            $isItem = str_starts_with($text, '-');

            if (preg_match('/^ignoreErrors:\s*(#.*)?$/', $text) === 1) {
                $keyIndent = $indent;
                $itemIndent = null;

                continue;
            }

            if ($keyIndent === null || ($indent <= $keyIndent && ! $isItem)) {
                $keyIndent = null;

                continue;
            }

            $itemIndent ??= $isItem ? $indent : null;

            if ($isItem && $indent === $itemIndent) {
                $entries[] = [ltrim(substr($text, 1))];
            } elseif ($entries !== []) {
                $entries[array_key_last($entries)][] = $text;
            }
        }

        return $entries;
    }

    /**
     * Get the number of errors the NEON entry ignores, which is its count key, or one without it.
     *
     * @param  list<string>  $lines
     */
    protected function countOfNeonEntry(array $lines): int
    {
        foreach ($lines as $index => $text) {
            $pattern = $index === 0 && str_starts_with($text, '{') ? '/[{,]\s*count:\s*(\d+)\s*[},]/' : '/^count:\s*(\d+)\s*(#.*)?$/';

            if (preg_match($pattern, $text, $match) === 1) {
                return (int) $match[1];
            }
        }

        return 1;
    }
}
