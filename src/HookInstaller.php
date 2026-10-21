<?php

namespace ClaudioDekker\LaravelGuardrails;

/**
 * @internal
 */
class HookInstaller
{
    /**
     * The line that marks a hook as written by the package.
     */
    protected const MARKER = 'Installed by claudiodekker/laravel-guardrails.';

    /**
     * Create an installer of the pre-commit hook at the given path, for the project in the given folder of the working tree.
     */
    public function __construct(public readonly string $path, public readonly string $directory = '')
    {
        //
    }

    /**
     * Create an installer for the project's pre-commit hook, wherever git keeps its hooks.
     */
    public static function for(Repository $repository): self
    {
        return new self($repository->hooksPath().'/pre-commit', $repository->prefix());
    }

    /**
     * Get the shell command that runs the guardrails over the staged files from the root of the working tree.
     */
    public function command(): string
    {
        if ($this->directory === '') {
            return 'vendor/bin/guardrails --staged';
        }

        return '(cd '.escapeshellarg($this->directory).' && vendor/bin/guardrails --staged)';
    }

    /**
     * Put the hook in place, leaving a hook the package did not write alone.
     */
    public function install(): HookInstallation
    {
        if (! is_file($this->path)) {
            $this->write();

            return HookInstallation::INSTALLED;
        }

        if (! $this->isOwn()) {
            return HookInstallation::FOREIGN;
        }

        if (file_get_contents($this->path) === $this->hook() && $this->isRunnable()) {
            return HookInstallation::UNCHANGED;
        }

        $this->write();

        return HookInstallation::UPDATED;
    }

    /**
     * Remove the hook if the package wrote it, and get whether it did.
     */
    public function uninstall(): bool
    {
        if (! is_file($this->path) || ! $this->isOwn()) {
            return false;
        }

        return unlink($this->path);
    }

    /**
     * Get the hook script the package installs.
     */
    protected function hook(): string
    {
        $directory = escapeshellarg($this->directory === '' ? '.' : $this->directory);

        return str_replace('{directory}', $directory, (string) file_get_contents(dirname(__DIR__).'/stubs/pre-commit'));
    }

    /**
     * Determine if the hook in place was written by the package.
     */
    protected function isOwn(): bool
    {
        return str_contains((string) file_get_contents($this->path), static::MARKER);
    }

    /**
     * Determine if git can run the hook in place, which on Windows needs no executable bit.
     */
    protected function isRunnable(): bool
    {
        return PHP_OS_FAMILY === 'Windows' || is_executable($this->path);
    }

    /**
     * Write the hook script and make it executable.
     */
    protected function write(): void
    {
        if (! is_dir(dirname($this->path))) {
            mkdir(dirname($this->path), 0755, true);
        }

        file_put_contents($this->path, $this->hook());
        chmod($this->path, 0755);
    }
}
