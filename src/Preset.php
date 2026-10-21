<?php

namespace ClaudioDekker\LaravelGuardrails;

/**
 * @internal
 */
class Preset
{
    /**
     * Create a preset of the given name from the pack, with the given checks and an optional one-line description.
     *
     * @param  list<string>  $checks
     */
    public function __construct(public readonly Pack $pack, public readonly string $name, public readonly array $checks, public readonly string $description = '')
    {
        //
    }

    /**
     * Get the preset's full name, which is its pack's name and its own, so two packs may name a preset alike.
     */
    public function id(): string
    {
        return "{$this->pack->name}:{$this->name}";
    }

    /**
     * Get the preset's name, pack, size and, when it has one, description, as one line.
     */
    public function label(): string
    {
        $label = "{$this->name} ({$this->pack->name}), ".count($this->checks).' checks';

        return $this->description === '' ? $label : "{$label}: {$this->description}";
    }
}
