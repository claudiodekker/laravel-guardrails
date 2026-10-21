<?php

namespace ClaudioDekker\LaravelGuardrails;

use ClaudioDekker\LaravelGuardrails\Projects\Directory;
use ClaudioDekker\LaravelGuardrails\Projects\Revision;
use InvalidArgumentException;
use RuntimeException;

/**
 * A project holds the regular files under its folder, by their paths from the project root.
 *
 * @api
 *
 * @final
 */
class Project
{
    /**
     * The paths of the project's files, sorted, once listed.
     *
     * @var list<string>|null
     */
    protected ?array $paths = null;

    /**
     * The paths of the project's files as keys, once listed.
     *
     * @var array<string, true>|null
     */
    protected ?array $known = null;

    /**
     * The paths each pattern matched, once asked.
     *
     * @var array<string, list<string>>
     */
    protected array $matches = [];

    /**
     * The folders the project's files sit in, at every depth, sorted, once listed.
     *
     * @var list<string>|null
     */
    protected ?array $folders = null;

    /**
     * The contents of each file read so far, by its path.
     *
     * @var array<string, string>
     */
    protected array $contents = [];

    /**
     * The layout of the project, once resolved.
     */
    protected ?Layout $layout = null;

    /**
     * The PHPUnit configuration of the project, once read.
     */
    protected ?PhpUnit $phpUnit = null;

    /**
     * Create the project whose files the source lists and reads.
     */
    protected function __construct(protected Directory|Revision $source)
    {
        //
    }

    /**
     * Get the project in the given folder of a git working tree, which holds the files git tracks there, as the folder holds them.
     *
     * @internal
     *
     * @throws RuntimeException
     */
    public static function inDirectory(string $directory): self
    {
        return new self(Directory::tracked($directory));
    }

    /**
     * Get the project in the given folder, which holds every file under it, whether git tracks it or not.
     *
     * @internal
     */
    public static function onDisk(string $folder): self
    {
        return new self(Directory::walked($folder));
    }

    /**
     * Get the project as the given revision of its git repository holds it, which is the index for ":" and a commit for anything else.
     *
     * @internal
     */
    public static function atRevision(Git $git, string $revision): self
    {
        return new self(new Revision($git, $revision));
    }

    /**
     * Get the project of a file from nowhere, which holds no file.
     *
     * @internal
     */
    public static function nowhere(): self
    {
        return new self(new Directory('', []));
    }

    /**
     * Get the contents of the file at the given path from the project root, or null when the project lacks it.
     *
     * @throws RuntimeException
     */
    public function read(string $path): ?string
    {
        if (! isset($this->known()[$path])) {
            return null;
        }

        return $this->contents[$path] ??= $this->source->read($path);
    }

    /**
     * Get the paths of the files that match the pattern, from the project root, with a wildcard matching within one folder name.
     *
     * @return list<string>
     *
     * @throws RuntimeException
     */
    public function files(string $pattern): array
    {
        return $this->matches[$pattern] ??= $this->matching($pattern);
    }

    /**
     * Get the paths of the folders that match the pattern, from the project root, with a wildcard matching within one folder name.
     *
     * @return list<string>
     *
     * @throws RuntimeException
     */
    public function folders(string $pattern): array
    {
        return array_values(array_filter($this->allFolders(), fn (string $folder) => fnmatch($pattern, $folder, FNM_PATHNAME)));
    }

    /**
     * Get the paths of every file of the project, from the project root, sorted, listing them once.
     *
     * @internal
     *
     * @return list<string>
     *
     * @throws RuntimeException
     */
    public function paths(): array
    {
        if ($this->paths === null) {
            $paths = $this->source->paths();

            sort($paths, SORT_STRING);

            $this->paths = $paths;
        }

        return $this->paths;
    }

    /**
     * Describe the file at the given path for a message, naming the revision that holds it when it comes from one, as "the staged phpunit.xml" or "composer.json at 1a2b3c4".
     *
     * @internal
     *
     * @throws RuntimeException
     */
    public function describe(string $path): string
    {
        return $this->source->describe($path);
    }

    /**
     * Read the JSON file at the given path as an array, or an empty one when the project lacks it.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function manifest(string $path): array
    {
        $json = $this->read($path);

        return $json === null ? [] : Catalog::decode($json, $this->describe($path));
    }

    /**
     * Get the namespaces autoload-dev maps, which only exist in development.
     *
     * @return list<string>
     */
    public function devNamespaces(): array
    {
        return $this->layout()->devNamespaces();
    }

    /**
     * Get the layout of the project, resolving it once.
     *
     * @internal
     */
    public function layout(): Layout
    {
        return $this->layout ??= Layout::resolve($this);
    }

    /**
     * Get the PHPUnit configuration of the project, reading it once, or null when the project has none.
     *
     * @internal
     */
    public function phpUnit(): ?PhpUnit
    {
        return $this->phpUnit ??= PhpUnit::of($this);
    }

    /**
     * Get the paths of the files as keys, listing them once.
     *
     * @return array<string, true>
     *
     * @throws RuntimeException
     */
    protected function known(): array
    {
        return $this->known ??= array_fill_keys($this->paths(), true);
    }

    /**
     * Get the paths that match the pattern: the path itself for a pattern with no wildcard, else the ones fnmatch accepts.
     *
     * @return list<string>
     *
     * @throws RuntimeException
     */
    protected function matching(string $pattern): array
    {
        if (strpbrk($pattern, '*?[') === false) {
            return isset($this->known()[$pattern]) ? [$pattern] : [];
        }

        return array_values(array_filter($this->paths(), fn (string $path) => fnmatch($pattern, $path, FNM_PATHNAME)));
    }

    /**
     * Get the folders the project's files sit in, each once, at every depth, sorted, listing them once.
     *
     * @return list<string>
     *
     * @throws RuntimeException
     */
    protected function allFolders(): array
    {
        if ($this->folders === null) {
            $found = [];

            foreach ($this->paths() as $path) {
                for ($folder = dirname($path); $folder !== '.'; $folder = dirname($folder)) {
                    $found[$folder] = true;
                }
            }

            $folders = array_keys($found);

            sort($folders, SORT_STRING);

            $this->folders = $folders;
        }

        return $this->folders;
    }
}
