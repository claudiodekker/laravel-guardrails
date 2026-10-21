<?php

namespace ClaudioDekker\LaravelGuardrails;

use InvalidArgumentException;
use JsonException;

/**
 * @internal
 */
class Catalog
{
    /**
     * The name of the package, whose pack holds the built-in checks and presets.
     */
    public const PACKAGE = 'claudiodekker/laravel-guardrails';

    /**
     * The name Composer gives a root project without one.
     */
    protected const ROOT = '__root__';

    /**
     * Create a catalog of the given packs, in the order their checks are offered.
     *
     * @param  list<Pack>  $packs
     */
    public function __construct(public readonly array $packs)
    {
        //
    }

    /**
     * Discover the packs of the project in the given directory: the built-in one, then each installed package that provides checks, then the project itself.
     *
     * @throws InvalidArgumentException
     */
    public static function discover(string $directory): self
    {
        $root = self::manifest($directory.'/composer.json');
        $vendor = is_string($root['config']['vendor-dir'] ?? null) ? $root['config']['vendor-dir'] : 'vendor';
        $installed = self::manifest($directory.'/'.$vendor.'/composer/installed.json');

        $packs = [Pack::fromManifest(self::PACKAGE, self::manifest(dirname(__DIR__).'/composer.json'))];

        foreach (is_array($installed['packages'] ?? null) ? $installed['packages'] : [] as $package) {
            if (
                is_array($package)
                && is_string($package['name'] ?? null)
                && $package['name'] !== self::PACKAGE
            ) {
                $packs[] = Pack::fromManifest($package['name'], $package);
            }
        }

        $name = is_string($root['name'] ?? null) ? $root['name'] : self::ROOT;

        if ($name !== self::PACKAGE) {
            $packs[] = Pack::fromManifest($name, $root, own: true);
        }

        return new self(array_values(array_filter($packs)));
    }

    /**
     * Get the catalog without the packs of the given packages, or without every package's pack when the list holds "*", keeping the project's own.
     *
     * @param  list<string>  $packages
     */
    public function without(array $packages): self
    {
        return new self(array_values(array_filter($this->packs, fn (Pack $pack) => ! self::names($packages, $pack))));
    }

    /**
     * Determine if the list of packages names the pack, by its name or by "*" for every package but the project's own.
     *
     * @param  list<string>  $packages
     */
    public static function names(array $packages, Pack $pack): bool
    {
        return self::entryNaming($packages, $pack) !== null;
    }

    /**
     * Get the entry of the list of packages that names the pack, or null when none does.
     *
     * @param  list<string>  $packages
     */
    public static function entryNaming(array $packages, Pack $pack): ?string
    {
        if (in_array($pack->name, $packages, true)) {
            return $pack->name;
        }

        return ! $pack->own && in_array('*', $packages, true) ? '*' : null;
    }

    /**
     * Get the pack of the given package, if the catalog holds it.
     */
    public function pack(string $name): ?Pack
    {
        foreach ($this->packs as $pack) {
            if ($pack->name === $name) {
                return $pack;
            }
        }

        return null;
    }

    /**
     * Get the pack that provides the check of the given class, if any does.
     */
    public function provider(string $class): ?Pack
    {
        foreach ($this->packs as $pack) {
            if (in_array($class, $pack->checks, true)) {
                return $pack;
            }
        }

        return null;
    }

    /**
     * Get the classes of every check the packs provide, in pack order, each once.
     *
     * @return list<string>
     */
    public function checks(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (Pack $pack) => $pack->checks, $this->packs))));
    }

    /**
     * Get every preset the packs provide, in pack order.
     *
     * @return list<Preset>
     */
    public function presets(): array
    {
        return array_merge(...array_map(fn (Pack $pack) => $pack->presets(), $this->packs));
    }

    /**
     * Get the groups of every pack's existing checks, in pack order.
     *
     * @return list<Group>
     */
    public function groups(): array
    {
        return array_merge(...array_map(fn (Pack $pack) => $pack->groups(), $this->packs));
    }

    /**
     * Get the preset of the given name, which is its full "package:name" or its own name when only one pack uses it.
     *
     * @throws InvalidArgumentException
     */
    public function preset(string $name): Preset
    {
        $presets = $this->presets();
        $matches = array_values(array_filter($presets, fn (Preset $preset) => $preset->id() === $name || $preset->name === $name));

        if (count($matches) === 1) {
            return $matches[0];
        }

        $ids = implode(', ', array_map(fn (Preset $preset) => $preset->id(), $presets));

        if ($matches === []) {
            throw new InvalidArgumentException("Preset {$name} does not exist. The presets are: {$ids}.");
        }

        $matching = implode(', ', array_map(fn (Preset $preset) => $preset->id(), $matches));

        throw new InvalidArgumentException("Preset {$name} is ambiguous. Name it in full: {$matching}.");
    }

    /**
     * Read the JSON file at the given path as an array, or an empty one when the file does not exist.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public static function manifest(string $path): array
    {
        return is_file($path) ? self::decode((string) file_get_contents($path), $path) : [];
    }

    /**
     * Decode the JSON of the manifest at the given path as an array, which is empty when the JSON holds no object.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public static function decode(string $json, string $path): array
    {
        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("{$path} is not valid JSON: {$exception->getMessage()}");
        }

        return is_array($manifest) ? $manifest : [];
    }
}
