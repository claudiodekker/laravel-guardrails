<?php

namespace ClaudioDekker\LaravelGuardrails\Tests\Support;

use ClaudioDekker\LaravelGuardrails\Catalog;
use ClaudioDekker\LaravelGuardrails\HookInstaller;
use ClaudioDekker\LaravelGuardrails\Process;
use ClaudioDekker\LaravelGuardrails\ProcessResult;
use ClaudioDekker\LaravelGuardrails\Repository;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ScratchRepository
{
    /**
     * The checks the tests select unless they say otherwise.
     *
     * @var list<string>
     */
    public const CHECKS = [
        'ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\FilesWithinTokenBudget',
        'ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes',
        'ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Tests\\NoDocblocksInTests',
        'ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Structure\\OneClassPerFile',
        'ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Enums\\EnumCasesDocumentedAlike',
        'ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Tests\\TestsInTestSuites',
        'ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Structure\\ConcernsHoldOnlyTraits',
    ];

    /**
     * The arguments that make env clear the variables CI services set.
     *
     * @var list<string>
     */
    protected const CLEAR_CI = ['-u', 'CI', '-u', 'GITHUB_ACTIONS', '-u', 'GITLAB_CI', '-u', 'JENKINS_URL'];

    /**
     * The linked worktrees and clones added beside the repository.
     *
     * @var list<self>
     */
    protected array $siblings = [];

    /**
     * Create a handle on the scratch repository at the given path.
     */
    public function __construct(public readonly string $path)
    {
        //
    }

    /**
     * Call the callback with a fresh scratch repository, then remove the repository.
     *
     * @template TReturn
     *
     * @param  callable(self): TReturn  $callback
     * @return TReturn
     */
    public static function using(callable $callback): mixed
    {
        $repository = self::make();

        try {
            return $callback($repository);
        } finally {
            $repository->delete();
        }
    }

    /**
     * Make an empty scratch repository.
     */
    public static function make(): self
    {
        $path = sys_get_temp_dir().'/guardrails-'.bin2hex(random_bytes(6));

        mkdir($path, 0755, true);

        $repository = new self((string) realpath($path));
        $repository->git('init', '--quiet', '--initial-branch=main');

        return $repository;
    }

    /**
     * Get a handle on a directory of the working tree, removed along with the repository.
     */
    public function directory(string $directory): self
    {
        return new self($this->path.'/'.$directory);
    }

    /**
     * Write a file of the given size in bytes into the working tree.
     */
    public function writeBytes(string $file, int $bytes): static
    {
        return $this->write($file, str_repeat('a', $bytes));
    }

    /**
     * Write a file with the given contents into the working tree.
     */
    public function write(string $file, string $contents): static
    {
        $directory = dirname($this->path.'/'.$file);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($this->path.'/'.$file, $contents);

        return $this;
    }

    /**
     * Select the given checks in guardrails.php, each by its full class name, and put the given keys under extra.guardrails in composer.json, keeping the rest of that file.
     *
     * @param  list<string>  $checks
     * @param  array<string, mixed>  $guardrails
     */
    public function select(array $checks = self::CHECKS, array $guardrails = []): static
    {
        $entries = implode('', array_map(fn (string $class) => "    \\{$class}::class,\n", $checks));

        $this->write('guardrails.php', "<?php\n\nreturn [\n{$entries}];\n");

        if ($guardrails === []) {
            return $this;
        }

        $path = $this->path.'/composer.json';
        $manifest = is_file($path) ? json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : [];
        $manifest['extra']['guardrails'] = $guardrails;

        return $this->write('composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * Select the checks of the given built-in preset in guardrails.php.
     */
    public function selectPreset(string $name): static
    {
        return $this->select(Catalog::discover(dirname(__DIR__, 2))->preset($name)->checks);
    }

    /**
     * Copy the files of the given directory into the working tree.
     */
    public function copy(string $directory): static
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $this->write(substr($file->getPathname(), strlen($directory) + 1), (string) file_get_contents($file->getPathname()));
        }

        return $this;
    }

    /**
     * Stage a file.
     */
    public function stage(string $file): static
    {
        $this->git('add', '--', $file);

        return $this;
    }

    /**
     * Stage every file in the working tree.
     */
    public function stageAll(): static
    {
        $this->git('add', '--all');

        return $this;
    }

    /**
     * Commit what is staged without running the hook.
     */
    public function commitWithoutHook(): static
    {
        $this->git('-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '--quiet', '--no-verify', '--allow-empty', '-m', 'Seed');

        return $this;
    }

    /**
     * Set a git configuration value of the repository.
     */
    public function config(string $key, string $value): self
    {
        $this->git('config', $key, $value);

        return $this;
    }

    /**
     * Create a branch at the current commit, staying on the current branch.
     */
    public function branch(string $name): self
    {
        $this->git('branch', $name);

        return $this;
    }

    /**
     * Switch the working tree to the given branch.
     */
    public function checkout(string $branch): static
    {
        $this->git('checkout', '--quiet', $branch);

        return $this;
    }

    /**
     * Merge the given branches into the current one without committing, leaving the merge in progress, which fails on a conflict.
     */
    public function merge(string ...$branches): static
    {
        Process::run($this->path, ['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'merge', '--quiet', '--no-commit', '--no-ff', ...$branches]);

        return $this;
    }

    /**
     * Replace a file in the working tree with a symbolic link to the given target.
     */
    public function link(string $file, string $target): static
    {
        unlink($this->path.'/'.$file);
        symlink($target, $this->path.'/'.$file);

        return $this;
    }

    /**
     * Record the current commit as the remote's default branch, origin/main.
     */
    public function publish(): self
    {
        $this->git('update-ref', 'refs/remotes/origin/main', 'HEAD');
        $this->git('symbolic-ref', 'refs/remotes/origin/HEAD', 'refs/remotes/origin/main');

        return $this;
    }

    /**
     * Move a committed file and stage the move.
     */
    public function move(string $from, string $to): self
    {
        $directory = dirname($this->path.'/'.$to);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->git('mv', '--', $from, $to);

        return $this;
    }

    /**
     * Stage the removal of a committed file.
     */
    public function stageDeletion(string $file): static
    {
        $this->git('rm', '--quiet', '--', $file);

        return $this;
    }

    /**
     * Stage a submodule at the path that points at the given commit, without a repository behind it.
     */
    public function stageSubmodule(string $path, string $commit): static
    {
        $this->git('update-index', '--add', '--cacheinfo', "160000,{$commit},{$path}");

        return $this;
    }

    /**
     * Commit what is staged, running the hooks.
     */
    public function commit(): ProcessResult
    {
        return Process::run($this->path, ['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '--quiet', '-m', 'Change']);
    }

    /**
     * Install the package's pre-commit hook into the repository.
     */
    public function installHook(): static
    {
        HookInstaller::for(new Repository($this->path))->install();

        return $this;
    }

    /**
     * Put a vendor/bin/guardrails in the working tree that runs the package's binary.
     */
    public function withGuardrailsBinary(): static
    {
        $this->write('vendor/bin/guardrails', "#!/bin/sh\nexec \"".PHP_BINARY.'" "'.dirname(__DIR__, 2)."/bin/guardrails\" \"\$@\"\n");
        chmod($this->path.'/vendor/bin/guardrails', 0755);

        return $this;
    }

    /**
     * Write a composer.json that gets the package from this checkout, with the given keys and package folders on top.
     *
     * @param  array<string, mixed>  $manifest
     * @param  list<string>  $packages
     */
    public function writeComposerJson(array $manifest, array $packages = []): self
    {
        $package = dirname(__DIR__, 2);

        $repositories = [
            ['packagist.org' => false],
            ['type' => 'path', 'url' => $package, 'options' => ['versions' => ['claudiodekker/laravel-guardrails' => '1.0.0']]],
            ...$this->dependencyRepositories($package),
            ...array_map(fn (string $folder) => ['type' => 'path', 'url' => $folder], $packages),
        ];

        $defaults = [
            'name' => 'acme/app',
            'repositories' => $repositories,
            'config' => ['allow-plugins' => ['claudiodekker/laravel-guardrails' => true]],
        ];

        return $this->write('composer.json', json_encode([...$defaults, ...$manifest], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    /**
     * Get a path repository for each package the package needs at runtime, from this checkout's vendor folder, so no install needs the network.
     *
     * @return list<array<string, mixed>>
     */
    protected function dependencyRepositories(string $package): array
    {
        $installed = json_decode((string) file_get_contents($package.'/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
        $manifests = array_column($installed['packages'], null, 'name');
        $queue = array_keys(json_decode((string) file_get_contents($package.'/composer.json'), true, 512, JSON_THROW_ON_ERROR)['require']);
        $repositories = [];

        while ($queue !== []) {
            $name = array_shift($queue);

            if (! isset($manifests[$name]) || isset($repositories[$name])) {
                continue;
            }

            $repositories[$name] = [
                'type' => 'path',
                'url' => $package.'/vendor/'.$name,
                'options' => ['versions' => [$name => ltrim($manifests[$name]['version'], 'v')]],
            ];

            $queue = [...$queue, ...array_keys($manifests[$name]['require'] ?? [])];
        }

        return array_values($repositories);
    }

    /**
     * Run composer install with the CI variables cleared and the given ones set.
     */
    public function composerInstall(string ...$variables): ProcessResult
    {
        return Process::run($this->path, ['env', ...self::CLEAR_CI, ...$variables, 'composer', 'install', '--no-interaction', '--no-progress']);
    }

    /**
     * Add a linked worktree beside the repository and get a handle on it.
     */
    public function worktree(string $name): self
    {
        $path = $this->path.'-'.$name;

        $this->git('worktree', 'add', '--quiet', '--detach', $path);

        return $this->siblings[] = new self($path);
    }

    /**
     * Clone the last commit of the given branch beside the repository, as a shallow clone in CI does, and get a handle on the clone.
     */
    public function shallowClone(string $branch): self
    {
        $path = $this->path.'-shallow';

        $this->git('clone', '--quiet', '--depth=1', '--branch', $branch, 'file://'.$this->path, $path);

        return $this->siblings[] = new self($path);
    }

    /**
     * Get the path of the pre-commit hook, wherever git keeps the repository's hooks.
     */
    public function hookPath(): string
    {
        return rtrim($this->git('rev-parse', '--path-format=absolute', '--git-path', 'hooks')).'/pre-commit';
    }

    /**
     * Run the guardrails binary in the repository with the given arguments, with the CI variables cleared.
     */
    public function runGuardrails(string ...$arguments): ProcessResult
    {
        return $this->runGuardrailsOn([], ...$arguments);
    }

    /**
     * Run the guardrails binary in the repository with the given arguments, with the CI variables cleared and then the given ones set, as "CI=true".
     *
     * @param  list<string>  $variables
     */
    public function runGuardrailsOn(array $variables, string ...$arguments): ProcessResult
    {
        return Process::run($this->path, ['env', ...self::CLEAR_CI, ...$variables, PHP_BINARY, dirname(__DIR__, 2).'/bin/guardrails', ...$arguments]);
    }

    /**
     * Run the guardrails binary in the repository under the given PHP memory limit, with the CI variables cleared.
     */
    public function runGuardrailsWithMemoryLimit(string $limit, string ...$arguments): ProcessResult
    {
        return Process::run($this->path, ['env', ...self::CLEAR_CI, PHP_BINARY, '-d', "memory_limit={$limit}", dirname(__DIR__, 2).'/bin/guardrails', ...$arguments]);
    }

    /**
     * Run a git command in the repository and get its output.
     */
    public function git(string ...$arguments): string
    {
        return Process::run($this->path, ['git', ...$arguments])->throw()->output;
    }

    /**
     * Remove the scratch repository.
     */
    protected function delete(): void
    {
        foreach ($this->siblings as $sibling) {
            $sibling->delete();
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() && ! $file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->path);
    }
}
