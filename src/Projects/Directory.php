<?php

namespace ClaudioDekker\LaravelGuardrails\Projects;

use ClaudioDekker\LaravelGuardrails\Git;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * @internal
 */
class Directory
{
    /**
     * Create the source of the given files of the folder on disk, by their paths from the folder.
     *
     * @param  list<string>  $paths
     */
    public function __construct(protected string $directory, protected array $paths)
    {
        //
    }

    /**
     * Get the source of the files git tracks in the folder, leaving out a file the working tree no longer holds as a regular file.
     *
     * @throws RuntimeException
     */
    public static function tracked(string $directory): self
    {
        $git = new Git($directory);
        $tracked = Git::regularFiles($git->run('ls-files', '-s', '-z'));

        return new self($directory, array_values(array_filter($tracked, fn (string $path) => static::isRegularFile("{$directory}/{$path}"))));
    }

    /**
     * Get the source of every regular file under the folder, whether git tracks it or not.
     */
    public static function walked(string $directory): self
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        $paths = [];

        foreach ($files as $file) {
            if (static::isRegularFile($file->getPathname())) {
                $paths[] = $files->getSubPathname();
            }
        }

        return new self($directory, $paths);
    }

    /**
     * Get the paths of the files, from the folder.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * Get the contents of the file at the given path from the folder.
     */
    public function read(string $path): string
    {
        return (string) file_get_contents("{$this->directory}/{$path}");
    }

    /**
     * Describe the file at the given path for a message, which is its path.
     */
    public function describe(string $path): string
    {
        return $path;
    }

    /**
     * Determine if the file at the given absolute path is a regular file, which a symbolic link is not.
     */
    protected static function isRegularFile(string $file): bool
    {
        return is_file($file) && ! is_link($file);
    }
}
