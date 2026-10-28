<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Package\Concerns;

use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Layout;
use InvalidArgumentException;

/**
 * @internal
 */
trait CoversFolders
{
    /**
     * The folders the check covers, each by its path from the project root, with a wildcard matching one folder name.
     *
     * @var list<string>
     */
    protected array $folders;

    /**
     * Create the check over the given folders, each by its path from the project root, with a wildcard matching one folder name.
     *
     * @param  list<string>  $folders
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $folders = ['src'])
    {
        $this->folders = array_map(fn (string $folder) => Layout::normalize($folder), $folders);

        if ($this->folders === [] || in_array('', $this->folders, true)) {
            $name = static::name();

            throw new InvalidArgumentException("{$name} takes the folders it covers below the project root, as new {$name}(['src']) or new {$name}(['packages/*/src']).");
        }
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return Kind::except(Kind::TESTS);
    }

    /**
     * Get the one-line description of the files the check reads, naming the folders it covers.
     */
    public function scope(): string
    {
        return parent::scope().' under '.implode(', ', $this->folders);
    }

    /**
     * Determine if the check reads the file, which sits in one of the folders it covers.
     */
    protected function reads(File $file): bool
    {
        foreach ($this->folders as $folder) {
            if (Layout::holds($folder, $file->path)) {
                return parent::reads($file);
            }
        }

        return false;
    }
}
