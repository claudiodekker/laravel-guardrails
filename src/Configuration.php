<?php

namespace ClaudioDekker\LaravelGuardrails;

use ArgumentCountError;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * @internal
 */
class Configuration
{
    /**
     * The line printed when no checks are selected.
     */
    public const HINT = 'No guardrails selected. Run vendor/bin/guardrails install to choose them.';

    /**
     * Create a configuration from the selected checks, by class name or as an instance, and the packages not to discover.
     *
     * @param  list<string|Check>  $checks
     * @param  list<string>  $dontDiscover
     */
    public function __construct(public readonly array $checks = [], public readonly array $dontDiscover = [])
    {
        //
    }

    /**
     * Read the configuration of the project in the given directory: the checks its guardrails.php returns, and the "extra.guardrails" key of its composer.json.
     *
     * @throws InvalidArgumentException
     */
    public static function fromProject(string $directory): self
    {
        $configuration = self::fromComposerJson($directory);
        $path = $directory.'/'.ConfigurationFile::NAME;

        if (! is_file($path)) {
            return $configuration;
        }

        return new self(self::load($path), $configuration->dontDiscover);
    }

    /**
     * Read the part of the configuration the composer.json of the project in the given directory holds: the packages not to discover.
     *
     * @throws InvalidArgumentException
     */
    public static function fromComposerJson(string $directory): self
    {
        $path = $directory.'/composer.json';

        return new self([], self::ignoredPackages(Catalog::manifest($path), $path));
    }

    /**
     * Read the configuration from the files the index holds, so that a change that is not staged cannot change the verdict, falling back to the guardrails.php on disk while git does not track it yet.
     *
     * @throws InvalidArgumentException
     */
    public static function fromIndex(Project $index, string $directory): self
    {
        $dontDiscover = self::ignoredPackages($index->manifest('composer.json'), $index->describe('composer.json'));
        $code = $index->read(ConfigurationFile::NAME);

        if ($code === null) {
            $path = $directory.'/'.ConfigurationFile::NAME;

            return new self(is_file($path) ? self::load($path) : [], $dontDiscover);
        }

        $path = self::stage($code, $directory);

        try {
            return new self(self::load($path, $index->describe(ConfigurationFile::NAME)), $dontDiscover);
        } finally {
            self::discard($path);
        }
    }

    /**
     * Write the code to a new file in the given directory, which is removed when the process ends however it ends, and get its path.
     *
     * @throws RuntimeException
     */
    protected static function stage(string $code, string $directory): string
    {
        $path = is_dir($directory) && is_writable($directory) ? tempnam($directory, '.guardrails-staged-') : false;

        if ($path === false || dirname($path) !== rtrim($directory, '/')) {
            throw new RuntimeException("{$directory} is not writable, so the staged guardrails.php cannot be loaded from there.");
        }

        register_shutdown_function(self::discard(...), $path);

        file_put_contents($path, $code);

        return $path;
    }

    /**
     * Remove the staged file at the path, if it is still there.
     */
    protected static function discard(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * Get the packages the "extra.guardrails" key of the composer.json manifest says not to discover.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected static function ignoredPackages(array $manifest, string $name): array
    {
        $guardrails = $manifest['extra']['guardrails'] ?? [];

        if (! is_array($guardrails)) {
            throw new InvalidArgumentException("{$name}: extra.guardrails must be an object.");
        }

        return Pack::strings($guardrails['dont-discover'] ?? [], "{$name}: extra.guardrails.dont-discover");
    }

    /**
     * Load the checks the guardrails.php at the given path returns, which must be a list of class names and check instances, naming each class once unless every entry of it is an instance, calling the file by the name in messages.
     *
     * @return list<string|Check>
     *
     * @throws InvalidArgumentException
     */
    protected static function load(string $path, ?string $name = null): array
    {
        $name ??= $path;

        try {
            $checks = self::returned($path);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException("{$name} could not be loaded: {$exception->getMessage()}");
        }

        if (! is_array($checks) || ! array_is_list($checks)) {
            throw new InvalidArgumentException("{$name} must return a list of checks.");
        }

        $seen = [];
        $named = [];

        foreach ($checks as $index => $check) {
            if (! is_string($check) && ! $check instanceof Check) {
                throw new InvalidArgumentException("{$name}: entry ".($index + 1).' must be a check class name or a check instance.');
            }

            if (
                $check instanceof Check
                && $check->label() === null
                && str_contains($check::class, "@anonymous\0")
            ) {
                throw new InvalidArgumentException("{$name}: entry ".($index + 1)." is an anonymous class. Name it with ->as('...').");
            }

            $class = $check instanceof Check ? $check::class : ltrim($check, '\\');

            if (isset($seen[$class]) && (is_string($check) || isset($named[$class]))) {
                throw new InvalidArgumentException("{$name}: entry ".($index + 1)." selects {$class} again, after entry {$seen[$class]}. Keep one entry per check, or configure each one.");
            }

            $seen[$class] ??= $index + 1;

            if (is_string($check)) {
                $named[$class] = true;
            }
        }

        return $checks;
    }

    /**
     * Get what the file at the given path returns.
     */
    protected static function returned(string $path): mixed
    {
        return require $path;
    }

    /**
     * Get the class of every selected check, in order.
     *
     * @return list<string>
     */
    public function classes(): array
    {
        return array_map(fn (string|Check $check) => $check instanceof Check ? $check::class : $check, $this->checks);
    }

    /**
     * Get the checks selected as an instance, configured through their constructors, in order.
     *
     * @return list<Check>
     */
    public function instances(): array
    {
        return array_values(array_filter($this->checks, fn (string|Check $check) => $check instanceof Check));
    }

    /**
     * Get the packs the project discovers: every pack of the catalog but the ones it does not want.
     */
    public function discoverable(Catalog $catalog): Catalog
    {
        return $catalog->without($this->dontDiscover);
    }

    /**
     * Get the selected checks, in the order guardrails.php lists them, failing on one that is missing or comes from a package the project does not discover.
     *
     * @return list<Check>
     *
     * @throws InvalidArgumentException
     */
    public function checks(Catalog $catalog): array
    {
        return array_map(fn (string|Check $check) => $this->resolve($check, $catalog), $this->checks);
    }

    /**
     * Get the check as selected: the instance itself, or a new check of the class.
     *
     * @throws InvalidArgumentException
     */
    protected function resolve(string|Check $check, Catalog $catalog): Check
    {
        $class = $check instanceof Check ? $check::class : $check;
        $pack = $catalog->provider($class);

        $entry = $pack === null ? null : Catalog::entryNaming($this->dontDiscover, $pack);

        if ($pack !== null && $entry !== null) {
            throw new InvalidArgumentException("{$class} comes from {$pack->name}, which extra.guardrails.dont-discover ignores through its entry {$entry}. Remove {$entry} from dont-discover, or run vendor/bin/guardrails install to deselect it.");
        }

        if ($check instanceof Check) {
            return $check;
        }

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Check class {$class} does not exist. Require the package that provides it again, or run vendor/bin/guardrails install to deselect it.");
        }

        if (! is_subclass_of($class, Check::class)) {
            throw new InvalidArgumentException("{$class} must extend ".Check::class.'.');
        }

        try {
            return new $class;
        } catch (ArgumentCountError) {
            $short = basename(str_replace('\\', '/', $class));

            throw new InvalidArgumentException("{$class} takes options. Write it as new {$short}(...) in guardrails.php.");
        }
    }
}
