<?php

namespace ClaudioDekker\LaravelGuardrails\Projects;

use ClaudioDekker\LaravelGuardrails\Git;
use RuntimeException;

/**
 * @internal
 */
class Revision
{
    /**
     * The revision that names the index.
     */
    public const INDEX = ':';

    /**
     * The short hash of the commit, once resolved.
     */
    protected ?string $commit = null;

    /**
     * Create the source of the files the given revision of the git repository holds, under the folder the git handle runs in.
     */
    public function __construct(protected Git $git, protected string $revision)
    {
        //
    }

    /**
     * Get the paths of the regular files the revision holds under the project folder, from that folder.
     *
     * @return list<string>
     *
     * @throws RuntimeException
     */
    public function paths(): array
    {
        if ($this->revision === static::INDEX) {
            return Git::regularFiles($this->git->run('ls-files', '-s', '-z'));
        }

        return Git::regularFiles($this->git->run('ls-tree', '-r', '-z', $this->revision));
    }

    /**
     * Get the contents of the file at the given path from the project folder, as the revision holds it.
     *
     * @throws RuntimeException
     */
    public function read(string $path): string
    {
        return $this->git->run('show', $this->spec($path));
    }

    /**
     * Describe the file at the given path for a message, naming the revision that holds it: the index, or a commit by its short hash.
     *
     * @throws RuntimeException
     */
    public function describe(string $path): string
    {
        if ($this->revision === static::INDEX) {
            return "the staged {$path}";
        }

        $this->commit ??= rtrim($this->git->run('rev-parse', '--short', $this->revision));

        return "{$path} at {$this->commit}";
    }

    /**
     * Get the name git show reads the file at the given path by, which names the revision before the colon for a commit and nothing for the index.
     */
    protected function spec(string $path): string
    {
        return ($this->revision === static::INDEX ? '' : $this->revision).":./{$path}";
    }
}
