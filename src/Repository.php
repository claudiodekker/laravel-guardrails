<?php

namespace ClaudioDekker\LaravelGuardrails;

use ClaudioDekker\LaravelGuardrails\Projects\Revision;
use InvalidArgumentException;
use RuntimeException;
use WeakMap;

/**
 * @internal
 *
 * @phpstan-import-type Diff from Git
 */
class Repository
{
    /**
     * The git repository the files come from.
     */
    protected Git $git;

    /**
     * Whether the composer.json and phpunit.xml of each project parse, once checked.
     *
     * @var WeakMap<Project, bool>
     */
    protected WeakMap $parses;

    /**
     * Create a handle on the project in the given directory of a git working tree.
     */
    public function __construct(public readonly string $directory)
    {
        $this->git = new Git($directory);
        $this->parses = new WeakMap;
    }

    /**
     * Resolve the project in the given directory, which must be inside a git working tree.
     *
     * @throws RuntimeException
     */
    public static function fromDirectory(string $directory): self
    {
        $result = Process::run($directory, ['git', 'rev-parse', '--show-toplevel']);

        if (! $result->successful()) {
            throw new RuntimeException("{$directory} is not inside a git repository.");
        }

        return new self($directory);
    }

    /**
     * Get an added change of every file git tracks in the project, reading each file from the working tree only when it is reached, and no more than the start of a binary one.
     *
     * @return iterable<non-empty-list<Change>>
     */
    public function trackedChanges(): iterable
    {
        $project = Project::inDirectory($this->directory);
        $paths = $project->paths();
        $readable = array_diff($paths, array_keys($this->skipped($paths)));

        return (function () use ($readable, $project) {
            foreach ($readable as $path) {
                $file = $this->directory.'/'.$path;
                $head = (string) file_get_contents($file, length: File::BINARY_PROBE_BYTES);

                if (File::isBinary($head)) {
                    continue;
                }

                yield [Change::added(new File($path, (string) file_get_contents($file), $project->layout()->kindOf($path), $project))];
            }
        })();
    }

    /**
     * Get the paths of the regular files git tracks in the project.
     *
     * @return list<string>
     */
    public function trackedPaths(): array
    {
        return Git::regularFiles($this->git->run('ls-files', '-s', '-z'));
    }

    /**
     * Get the change of each file the index holds against the last commit, and during a merge against each parent.
     *
     * @return iterable<non-empty-list<Change>>
     */
    public function stagedChanges(): iterable
    {
        $parents = ['HEAD' => ['--cached']];

        foreach ($this->mergeHeads() as $commit) {
            $parents[$commit] = ['--cached', $commit];
        }

        return $this->changes($this->staged(), $parents, cached: true);
    }

    /**
     * Get the project as the index holds it.
     */
    public function staged(): Project
    {
        return $this->atRevision(Revision::INDEX);
    }

    /**
     * Determine if the working tree or the index holds a change to a tracked file that a run over commits does not read.
     */
    public function hasUncommittedChanges(): bool
    {
        return trim($this->git->run('status', '--porcelain', '--untracked-files=no')) !== '';
    }

    /**
     * Get the commits a merge in progress brings in, which MERGE_HEAD lists one per line, or none outside a merge.
     *
     * @return list<string>
     */
    protected function mergeHeads(): array
    {
        $path = rtrim($this->git->run('rev-parse', '--git-path', 'MERGE_HEAD'), "\n");
        $path = str_starts_with($path, '/') ? $path : $this->directory.'/'.$path;

        if (! is_file($path)) {
            return [];
        }

        return file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    }

    /**
     * Get the changes to the project's files between the given commit and HEAD.
     *
     * @return iterable<non-empty-list<Change>>
     */
    public function changesSince(string $commit): iterable
    {
        return $this->changes($this->atRevision('HEAD'), [$commit => [$commit, 'HEAD']]);
    }

    /**
     * Get the project as the given revision holds it.
     */
    protected function atRevision(string $revision): Project
    {
        return Project::atRevision($this->git, $revision);
    }

    /**
     * Get the commit where HEAD branched off the given ref.
     *
     * @throws MissingBase
     */
    public function mergeBase(string $ref): string
    {
        $commit = Process::run($this->directory, ['git', 'rev-parse', '--verify', '--quiet', "{$ref}^{commit}"]);

        if (! $commit->successful()) {
            throw new MissingBase("{$ref} does not exist.", refMissing: true);
        }

        $mergeBase = Process::run($this->directory, ['git', 'merge-base', rtrim($commit->output), 'HEAD']);

        if (! $mergeBase->successful()) {
            throw new MissingBase("HEAD shares no history with {$ref}.");
        }

        return rtrim($mergeBase->output);
    }

    /**
     * Determine if the repository is a shallow clone, which lacks the history before its oldest commits.
     */
    public function isShallow(): bool
    {
        return rtrim($this->git->run('rev-parse', '--is-shallow-repository')) === 'true';
    }

    /**
     * Get the absolute path of the hooks directory, which worktrees share and core.hooksPath may move.
     */
    public function hooksPath(): string
    {
        return rtrim($this->git->run('rev-parse', '--path-format=absolute', '--git-path', 'hooks'));
    }

    /**
     * Get the project's folder from the root of the working tree, which is empty at the root.
     */
    public function prefix(): string
    {
        return rtrim($this->git->run('rev-parse', '--show-prefix'), "/\n");
    }

    /**
     * Get the change of each file against every parent, from the file at its old path in the parent's revision to the file the project holds, or to none when the change deletes it, reading each file only when it is reached.
     *
     * @param  non-empty-array<string, list<string>>  $parents
     * @param  bool  $cached  Whether the attributes come from the index, rather than the working tree.
     * @return iterable<non-empty-list<Change>>
     */
    protected function changes(Project $project, array $parents, bool $cached = false): iterable
    {
        $perParent = array_values(array_map(fn (array $range) => $this->diffs($range), $parents));
        $diffs = $this->readable($this->common($perParent), $cached);
        $previousProjects = array_map(fn (string $revision) => $this->atRevision($revision), array_keys($parents));
        $blobs = Blobs::of($this->git, $this->blobs($diffs));

        return $this->read($diffs, $project, $previousProjects, $blobs);
    }

    /**
     * Get the diff of each file over the given range, by its path, with the patch of each edit.
     *
     * @param  list<string>  $range
     * @return array<string, Diff>
     */
    protected function diffs(array $range): array
    {
        $command = ['diff', ...$range, '--relative', ...Git::RENAMES];

        $raw = $this->git->run(...[...$command, '--raw', '-z', '--no-abbrev']);
        $patch = $this->git->patchHeaders(...[...$command, '--diff-filter=CMR', ...Git::DIFF_OPTIONS]);

        return Git::diffs($raw, $patch);
    }

    /**
     * Get the files the diff against every parent holds, in the order of the first parent's, each with its diff against every parent.
     *
     * @param  non-empty-list<array<string, Diff>>  $perParent
     * @return array<string, non-empty-list<Diff>>
     */
    protected function common(array $perParent): array
    {
        $common = [];

        foreach (array_keys($perParent[0]) as $path) {
            $diffs = array_column($perParent, $path);

            if (count($diffs) === count($perParent)) {
                $common[$path] = $diffs;
            }
        }

        return $common;
    }

    /**
     * Leave out the files no check reads by their path, and the file before a change that no check reads, before reading any of them.
     *
     * @param  array<string, non-empty-list<Diff>>  $diffs
     * @return array<string, non-empty-list<Diff>>
     */
    protected function readable(array $diffs, bool $cached): array
    {
        $sides = array_merge(...array_values($diffs));
        $skipped = $this->skipped(array_values(array_unique([...array_column($sides, 'path'), ...array_column($sides, 'previousPath')])), $cached);
        $readable = [];

        foreach ($diffs as $path => $perParent) {
            if (isset($skipped[$perParent[0]['path']])) {
                continue;
            }

            foreach ($perParent as $index => $diff) {
                if (isset($skipped[$diff['previousPath']])) {
                    $perParent[$index]['previousBlob'] = null;
                }
            }

            $readable[$path] = $perParent;
        }

        return $readable;
    }

    /**
     * Get the blob of every side of the diffs.
     *
     * @param  array<string, non-empty-list<Diff>>  $diffs
     * @return list<string>
     */
    protected function blobs(array $diffs): array
    {
        $sides = array_merge(...array_values($diffs));

        return array_values(array_filter([...array_column($sides, 'blob'), ...array_column($sides, 'previousBlob')]));
    }

    /**
     * Read the change of each file against every parent when it is reached, leaving out a file whose contents no check reads.
     *
     * @param  array<string, non-empty-list<Diff>>  $diffs
     * @param  list<Project>  $previousProjects
     * @return iterable<non-empty-list<Change>>
     */
    protected function read(array $diffs, Project $project, array $previousProjects, Blobs $blobs): iterable
    {
        foreach ($diffs as $perParent) {
            $file = $this->fileAfter($perParent, $project, $blobs);
            $changes = $this->changesOf($perParent, $file, $previousProjects, $blobs);

            if ($changes !== []) {
                yield $changes;
            }
        }
    }

    /**
     * Read the file the change leaves, or null when no parent's diff leaves one, or it holds binary data.
     *
     * @param  non-empty-list<Diff>  $perParent
     */
    protected function fileAfter(array $perParent, Project $project, Blobs $blobs): ?File
    {
        foreach ($perParent as $diff) {
            if ($diff['blob'] !== null) {
                return $this->file($diff['path'], $diff['blob'], $project, $blobs);
            }
        }

        return null;
    }

    /**
     * Get the change against each parent, which deletes the file against a parent whose diff deletes it and adds or edits it against the others, or none when the file holds binary data or the file a parent held cannot be read.
     *
     * @param  non-empty-list<Diff>  $perParent
     * @param  list<Project>  $previousProjects
     * @return list<Change>
     */
    protected function changesOf(array $perParent, ?File $file, array $previousProjects, Blobs $blobs): array
    {
        $changes = [];

        foreach ($perParent as $index => $diff) {
            $previous = $this->previousFile($diff, $previousProjects[$index], $blobs);

            if ($diff['blob'] === null) {
                if ($previous === null) {
                    return [];
                }

                $changes[] = Change::deleted($previous);

                continue;
            }

            if ($file === null) {
                return [];
            }

            $changes[] = $previous === null
                ? Change::added($file)
                : Change::from($previous, $file, Git::changedLines($diff['patch'], $file->lineCount()));
        }

        return $changes;
    }

    /**
     * Read the file before a change from its blob, or get null when there is none, it holds binary data, or the composer.json or phpunit.xml of the revision that held it does not parse, so the change is judged as one that adds the file.
     *
     * @param  Diff  $diff
     */
    protected function previousFile(array $diff, Project $project, Blobs $blobs): ?File
    {
        if ($diff['previousBlob'] === null) {
            return null;
        }

        $this->parses[$project] ??= $this->configParses($project);

        if (! $this->parses[$project]) {
            return null;
        }

        return $this->file($diff['previousPath'], $diff['previousBlob'], $project, $blobs);
    }

    /**
     * Determine if the composer.json and the phpunit.xml of the project parse.
     */
    protected function configParses(Project $project): bool
    {
        try {
            $project->layout();
            $project->phpUnit();
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * Read the file at the path from the blob, as the project holds it, or get null when there is no blob or it holds binary data.
     */
    protected function file(string $path, ?string $blob, Project $project, Blobs $blobs): ?File
    {
        if ($blob === null || File::isBinary($blobs->head($blob))) {
            return null;
        }

        return new File($path, $blobs->read($blob), $project->layout()->kindOf($path), $project);
    }

    /**
     * Get the paths among the given ones that every check skips without reading them, by their name or git's linguist-generated mark, as the keys of a set.
     *
     * @param  list<string>  $paths
     * @return array<string, true>
     */
    protected function skipped(array $paths, bool $cached = false): array
    {
        if ($paths === []) {
            return [];
        }

        $command = ['check-attr', ...($cached ? ['--cached'] : []), '--stdin', '-z', 'linguist-generated'];
        $entries = Git::entries($this->git->runWithInput(implode("\0", $paths), ...$command));
        $skipped = [];

        foreach (array_chunk($entries, 3) as [$path, , $value]) {
            if (in_array($value, ['set', 'true'], true) || File::isSkippedPath($path)) {
                $skipped[$path] = true;
            }
        }

        return $skipped;
    }
}
