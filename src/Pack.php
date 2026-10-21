<?php

namespace ClaudioDekker\LaravelGuardrails;

use InvalidArgumentException;

/**
 * @internal
 */
class Pack
{
    /**
     * Create a pack of the checks and presets the package of the given name provides, which may be the project's own.
     *
     * @param  list<string>  $checks
     * @param  array<string, list<string>>  $presets
     * @param  array<string, string>  $descriptions
     */
    public function __construct(public readonly string $name, public readonly array $checks = [], public readonly array $presets = [], public readonly bool $own = false, public readonly array $descriptions = [])
    {
        //
    }

    /**
     * Read the pack a package declares under "extra.guardrails.provides" in its decoded composer.json, or null when it declares none.
     *
     * @param  array<string, mixed>  $manifest
     *
     * @throws InvalidArgumentException
     */
    public static function fromManifest(string $name, array $manifest, bool $own = false): ?self
    {
        $provides = $manifest['extra']['guardrails']['provides'] ?? null;

        if ($provides === null) {
            return null;
        }

        if (! is_array($provides) || (array_is_list($provides) && $provides !== [])) {
            throw new InvalidArgumentException("{$name}: extra.guardrails.provides must be an object with \"checks\" and \"presets\".");
        }

        $presets = $provides['presets'] ?? [];

        if (! is_array($presets) || (array_is_list($presets) && $presets !== [])) {
            throw new InvalidArgumentException("{$name}: extra.guardrails.provides.presets must map each preset name to a list of class names.");
        }

        $lists = [];

        foreach ($presets as $preset => $classes) {
            $lists[(string) $preset] = self::strings($classes, "{$name}: extra.guardrails.provides.presets.{$preset}");
        }

        $descriptions = $provides['descriptions'] ?? [];

        if (
            ! is_array($descriptions)
            || (array_is_list($descriptions) && $descriptions !== [])
            || array_filter($descriptions, fn (mixed $description) => ! is_string($description)) !== []
        ) {
            throw new InvalidArgumentException("{$name}: extra.guardrails.provides.descriptions must map each preset name to a sentence.");
        }

        foreach (array_keys($descriptions) as $preset) {
            if (! isset($lists[(string) $preset])) {
                throw new InvalidArgumentException("{$name}: extra.guardrails.provides.descriptions describes the preset {$preset}, which the pack does not provide.");
            }
        }

        return new self($name, self::strings($provides['checks'] ?? [], "{$name}: extra.guardrails.provides.checks"), $lists, $own, $descriptions);
    }

    /**
     * Determine if the pack is the package's own, which holds the built-in checks.
     */
    public function isBuiltIn(): bool
    {
        return $this->name === Catalog::PACKAGE;
    }

    /**
     * Get the presets of the pack.
     *
     * @return list<Preset>
     */
    public function presets(): array
    {
        return array_map(fn (string $name) => new Preset($this, $name, $this->presets[$name], $this->descriptions[$name] ?? ''), array_keys($this->presets));
    }

    /**
     * Get the checks of the pack that exist, which are the ones a project may run.
     *
     * @return list<class-string<Check>>
     */
    public function existing(): array
    {
        return array_values(array_filter($this->checks, fn (string $class) => is_subclass_of($class, Check::class)));
    }

    /**
     * Get the checks the pack declares that do not exist or are not checks.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        return array_values(array_diff($this->checks, $this->existing()));
    }

    /**
     * Get the pack's existing checks in a group per namespace below the one they share, in the order they are declared.
     *
     * @return list<Group>
     */
    public function groups(): array
    {
        $checks = $this->existing();
        $shared = count($this->sharedNamespace($checks));
        $groups = [];

        foreach ($checks as $class) {
            $name = implode(Group::SEPARATOR, array_slice(explode('\\', $class), $shared, -1));
            $groups[$name][] = $class;
        }

        return array_map(fn (string $name, array $classes) => new Group($this, $name, $classes), array_keys($groups), $groups);
    }

    /**
     * Get the segments of the namespace every one of the given classes sits in.
     *
     * @param  list<class-string<Check>>  $classes
     * @return list<string>
     */
    protected function sharedNamespace(array $classes): array
    {
        $namespaces = array_map(fn (string $class) => array_slice(explode('\\', $class), 0, -1), $classes);
        $shared = array_shift($namespaces) ?? [];

        foreach ($namespaces as $namespace) {
            $length = 0;

            while ($length < count($shared) && ($namespace[$length] ?? null) === $shared[$length]) {
                $length++;
            }

            $shared = array_slice($shared, 0, $length);
        }

        return $shared;
    }

    /**
     * Require the value to be a list of strings, naming the key in the error otherwise.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    public static function strings(mixed $value, string $key): array
    {
        if (
            ! is_array($value)
            || ! array_is_list($value)
            || array_filter($value, fn (mixed $entry) => ! is_string($entry)) !== []
        ) {
            throw new InvalidArgumentException("{$key} must be a list of class names.");
        }

        return $value;
    }
}
