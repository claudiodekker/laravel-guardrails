<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Config;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * @api
 */
class DurationsAsProducts extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'A "_seconds" config value of whole minutes is written as a product, such as 12 * 60 * 60.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A value such as \'lifetime_seconds\' => 43200 makes the reader divide to see that it is 12 hours. This flags an integer over 60 that is a whole number of minutes, under a key ending in _seconds, in the config folder. Write it as 12 * 60 * 60 instead. A value of 60, seconds that are not whole minutes and other keys are left alone.';
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
        return 'Write the duration as a product of its units, such as 12 * 60 * 60, so it reads as hours and minutes.';
    }

    /**
     * Get a violation at each duration written as a total.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $items = array_filter($file->findInstanceOf(ArrayItem::class), fn (ArrayItem $item) => $this->isTotalOfMinutes($item));

        return array_map(Violation::at(...), array_values($items));
    }

    /**
     * Determine if the item is a "_seconds" key whose value is more than a minute and a whole number of minutes.
     */
    protected function isTotalOfMinutes(ArrayItem $item): bool
    {
        return $item->key instanceof String_
            && str_ends_with($item->key->value, '_seconds')
            && $item->value instanceof Int_
            && $item->value->value > 60
            && $item->value->value % 60 === 0;
    }
}
