<?php

namespace ClaudioDekker\LaravelGuardrails;

/**
 * @internal
 */
class Placement
{
    /**
     * Get the position for an entry of the class: after the last entry of a class before it in the given order, else before the first of a class after it, else last.
     *
     * @param  list<string|null>  $present
     * @param  list<string>  $classes
     */
    public static function of(array $present, array $classes, string $class): int
    {
        $ranks = array_flip($classes);
        $after = null;
        $before = null;

        foreach ($present as $index => $entry) {
            $rank = $entry === null ? null : ($ranks[$entry] ?? null);

            if ($rank === null) {
                continue;
            }

            if ($rank < $ranks[$class]) {
                $after = $index;
            } elseif ($before === null) {
                $before = $index;
            }
        }

        if ($after !== null) {
            return $after + 1;
        }

        return $before ?? count($present);
    }
}
