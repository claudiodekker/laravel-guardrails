<?php

namespace ClaudioDekker\LaravelGuardrails;

use InvalidArgumentException;
use PhpParser\PhpVersion;

/**
 * @internal
 */
class Layout
{
    /**
     * The folders a project autoloads when its composer.json names none.
     */
    protected const SOURCE_FOLDERS = ['app', 'src'];

    /**
     * The folder a project keeps its tests in when its composer.json names none.
     */
    protected const TESTS_FOLDERS = ['tests'];

    /**
     * The conventional folders of the kinds that live in folders of their own, by the kind's value.
     */
    protected const FOLDERS = [
        'config' => ['config'],
        'frontend' => ['resources/js', 'resources/css'],
        'lang' => ['lang', 'resources/lang'],
        'migrations' => ['database/migrations'],
        'routes' => ['routes'],
        'views' => ['resources/views'],
    ];

    /**
     * The sections of composer.json that name autoloaded paths.
     */
    protected const AUTOLOAD_SECTIONS = ['psr-4', 'psr-0', 'classmap', 'files'];

    /**
     * The kinds in the order a path is given to them when it sits under the roots of more than one at the same depth.
     */
    protected const PRECEDENCE = [Kind::TESTS, Kind::MIGRATIONS, Kind::CONFIG, Kind::LANG, Kind::ROUTES, Kind::VIEWS, Kind::FRONTEND, Kind::SOURCE];

    /**
     * The roots of each kind, by the kind's value.
     *
     * @var array<string, list<string>>
     */
    protected array $roots;

    /**
     * The namespaces autoload-dev maps, each ending in one backslash.
     *
     * @var list<string>
     */
    protected array $namespaces;

    /**
     * Create a layout from the roots of each kind: folders, files, or either with a wildcard in a folder name, from the project root, and the PHP version the project targets.
     *
     * @param  list<string>  $source
     * @param  list<string>  $tests
     * @param  list<string>  $config
     * @param  list<string>  $migrations
     * @param  list<string>  $lang
     * @param  list<string>  $routes
     * @param  list<string>  $views
     * @param  list<string>  $frontend
     * @param  list<string>  $namespaces
     */
    public function __construct(
        array $source = [],
        array $tests = [],
        array $config = [],
        array $migrations = [],
        array $lang = [],
        array $routes = [],
        array $views = [],
        array $frontend = [],
        array $namespaces = [],
        protected ?PhpVersion $php = null,
    ) {
        $this->namespaces = $namespaces;

        $this->roots = [
            Kind::SOURCE->value => $source,
            Kind::TESTS->value => $tests,
            Kind::CONFIG->value => $config,
            Kind::MIGRATIONS->value => $migrations,
            Kind::LANG->value => $lang,
            Kind::ROUTES->value => $routes,
            Kind::VIEWS->value => $views,
            Kind::FRONTEND->value => $frontend,
        ];
    }

    /**
     * Resolve the layout of the project from its composer.json and phpunit.xml, with Laravel's folders for whatever composer.json leaves unsaid.
     *
     * @throws InvalidArgumentException
     */
    public static function resolve(Project $project): self
    {
        $manifest = $project->manifest('composer.json');
        $source = self::autoload($manifest['autoload'] ?? null) ?: self::SOURCE_FOLDERS;
        $tests = self::autoload($manifest['autoload-dev'] ?? null) ?: self::TESTS_FOLDERS;
        $namespaces = self::namespaces($manifest['autoload-dev'] ?? null);

        foreach (self::packages($project, $manifest['repositories'] ?? null) as $folder => $package) {
            array_push($namespaces, ...self::namespaces($package['autoload-dev'] ?? null));
            array_push($source, ...self::within($folder, self::autoload($package['autoload'] ?? null)));
            array_push($tests, ...self::within($folder, self::autoload($package['autoload-dev'] ?? null)));
        }

        $source = array_values(array_unique($source));

        return new self(
            $source,
            array_values(array_unique([...$tests, ...($project->phpUnit()?->paths() ?? [])])),
            self::beside($project, $source, self::FOLDERS['config']),
            self::beside($project, $source, self::FOLDERS['migrations']),
            self::beside($project, $source, self::FOLDERS['lang']),
            self::beside($project, $source, self::FOLDERS['routes']),
            self::beside($project, $source, self::FOLDERS['views']),
            self::beside($project, $source, self::FOLDERS['frontend']),
            array_values(array_unique($namespaces)),
            self::phpVersion($manifest['require']['php'] ?? null),
        );
    }

    /**
     * Get the roots of the given kind.
     *
     * @return list<string>
     */
    public function roots(Kind $kind): array
    {
        return $this->roots[$kind->value] ?? [];
    }

    /**
     * Get the namespaces autoload-dev maps.
     *
     * @return list<string>
     */
    public function devNamespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * Get the PHP version the project targets, which is the lowest one composer.json requires, or null when it names none.
     */
    public function php(): ?PhpVersion
    {
        return $this->php;
    }

    /**
     * Get the kind of the file at the given path, which is the kind of the deepest root it sits under, or text under none.
     */
    public function kindOf(string $path): Kind
    {
        $kind = Kind::TEXT;
        $depth = -1;

        foreach (static::PRECEDENCE as $candidate) {
            foreach ($this->roots($candidate) as $root) {
                if (self::depth($root) > $depth && self::holds($root, $path)) {
                    $kind = $candidate;
                    $depth = self::depth($root);
                }
            }
        }

        return $kind;
    }

    /**
     * Determine if the path is the root or sits under it, with a wildcard in the root matching one folder name and any other root taken as written.
     */
    public static function holds(string $root, string $path): bool
    {
        if ($root === '') {
            return true;
        }

        $segments = explode('/', $path);

        if (count($segments) < self::depth($root)) {
            return false;
        }

        $prefix = implode('/', array_slice($segments, 0, self::depth($root)));

        return self::isPattern($root) ? fnmatch($root, $prefix, FNM_PATHNAME) : $prefix === $root;
    }

    /**
     * Get the lowest major and minor version the PHP constraint names, or null when it names none.
     */
    protected static function phpVersion(mixed $constraint): ?PhpVersion
    {
        if (! is_string($constraint) || preg_match_all('/\d+\.\d+/', $constraint, $matches) === 0) {
            return null;
        }

        $versions = array_map(PhpVersion::fromString(...), $matches[0]);

        usort($versions, fn (PhpVersion $a, PhpVersion $b) => $a->id <=> $b->id);

        return $versions[0];
    }

    /**
     * Get the paths the autoload section names, normalized from the project root.
     *
     * @return list<string>
     */
    protected static function autoload(mixed $section): array
    {
        if (! is_array($section)) {
            return [];
        }

        $paths = [];

        foreach (static::AUTOLOAD_SECTIONS as $name) {
            $entries = $section[$name] ?? [];

            foreach (is_array($entries) ? $entries : [] as $entry) {
                array_push($paths, ...array_filter(is_array($entry) ? $entry : [$entry], 'is_string'));
            }
        }

        return array_values(array_unique(array_map(self::normalize(...), $paths)));
    }

    /**
     * Get the namespace prefixes the psr-4 and psr-0 entries of the autoload section map, each ending in one backslash.
     *
     * @return list<string>
     */
    protected static function namespaces(mixed $section): array
    {
        $namespaces = [];

        foreach (['psr-4', 'psr-0'] as $name) {
            $entries = is_array($section) ? ($section[$name] ?? []) : [];

            foreach (is_array($entries) ? array_keys($entries) : [] as $prefix) {
                $prefix = trim((string) $prefix, '\\');

                if ($prefix !== '') {
                    $namespaces[] = "{$prefix}\\";
                }
            }
        }

        return $namespaces;
    }

    /**
     * Get the composer.json of each package the path repositories hold inside the project, by the package's folder from the project root.
     *
     * @return array<string, array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    protected static function packages(Project $project, mixed $repositories): array
    {
        $packages = [];

        foreach (is_array($repositories) ? $repositories : [] as $repository) {
            if (
                ! is_array($repository)
                || ($repository['type'] ?? null) !== 'path'
                || ! is_string($repository['url'] ?? null)
            ) {
                continue;
            }

            $url = self::normalize($repository['url']);

            if (str_starts_with($repository['url'], '/') || str_starts_with($url, '..')) {
                continue;
            }

            foreach ($project->files("{$url}/composer.json") as $manifest) {
                $packages[dirname($manifest)] = $project->manifest($manifest);
            }
        }

        return $packages;
    }

    /**
     * Get the paths from the project root, given from the folder.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    protected static function within(string $folder, array $paths): array
    {
        return array_map(fn (string $path) => self::normalize("{$folder}/{$path}"), $paths);
    }

    /**
     * Get the given folders, then each of them inside each source root and beside the root's parent, when it exists.
     *
     * @param  list<string>  $source
     * @param  list<string>  $folders
     * @return list<string>
     */
    protected static function beside(Project $project, array $source, array $folders): array
    {
        $found = $folders;

        foreach ($source as $root) {
            $folder = str_ends_with($root, '.php') ? dirname($root) : $root;

            foreach (array_unique([$folder, dirname($folder)]) as $candidate) {
                foreach ($folders as $name) {
                    if (! in_array($candidate, ['', '.'], true) && $project->folders("{$candidate}/{$name}") !== []) {
                        $found[] = "{$candidate}/{$name}";
                    }
                }
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Determine if the root holds a wildcard.
     */
    protected static function isPattern(string $root): bool
    {
        return str_contains($root, '*');
    }

    /**
     * Normalize a path from composer.json or phpunit.xml: forward slashes, no leading "./", no trailing slash, and the project root as an empty string.
     */
    public static function normalize(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return $path === '.' ? '' : (string) preg_replace('#^\./#', '', $path);
    }

    /**
     * Get the number of folders in the root, counting a file as one.
     */
    protected static function depth(string $root): int
    {
        return $root === '' ? 0 : substr_count($root, '/') + 1;
    }
}
