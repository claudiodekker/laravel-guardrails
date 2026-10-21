<?php

namespace ClaudioDekker\LaravelGuardrails;

use RuntimeException;

/**
 * @internal
 *
 * @phpstan-type Diff array{path: string, previousPath: string, blob: string|null, previousBlob: string|null, patch: string}
 */
class Git
{
    /**
     * The options that pin how git prints a patch, whatever the developer's diff settings.
     */
    public const DIFF_OPTIONS = [
        '--no-color',
        '--no-ext-diff',
        '--no-textconv',
        '--inter-hunk-context=0',
        '--diff-algorithm=myers',
        '--indent-heuristic',
        '--src-prefix=a/',
        '--dst-prefix=b/',
        '--submodule=short',
        '--unified=0',
    ];

    /**
     * The options that pin how git pairs a renamed file with its old path, whatever the developer's settings: half its contents must match, among up to git's default of 1000 files.
     */
    public const RENAMES = ['--find-renames=50%', '-l1000'];

    /**
     * The environment variables that change how git prints a patch.
     */
    protected const DIFF_VARIABLES = ['GIT_DIFF_OPTS', 'GIT_EXTERNAL_DIFF'];

    /**
     * The most bytes of a line of a patch read at once, so a line of any length stays out of memory.
     */
    protected const PATCH_CHUNK_BYTES = 16384;

    /**
     * The statuses of the copies, edits and renames, the entries of a raw diff that git prints a patch block for under --diff-filter=CMR.
     */
    protected const EDITS = ['C', 'M', 'R'];

    /**
     * The modes git gives a regular file, unlike a symbolic link or a submodule.
     */
    protected const REGULAR_FILE_MODES = ['100644', '100755'];

    /**
     * The status git gives a path a merge has not resolved.
     */
    protected const UNMERGED = 'U';

    /**
     * Create a handle on the git repository that holds the given directory.
     */
    public function __construct(public readonly string $directory)
    {
        //
    }

    /**
     * Run a git command and get its output.
     */
    public function run(string ...$arguments): string
    {
        return Process::run($this->directory, ['git', ...$arguments], environment: static::environment())->throw()->output;
    }

    /**
     * Run a git command with the given input on stdin and get its output.
     */
    public function runWithInput(string $input, string ...$arguments): string
    {
        return Process::run($this->directory, ['git', ...$arguments], $input, static::environment())->throw()->output;
    }

    /**
     * Run a git command and get the lines of its patch output that tell which lines each file changed: the line that opens the patch of a file, its hunk headers, and the notice of a file git does not diff.
     *
     * @throws RuntimeException
     */
    public function patchHeaders(string ...$arguments): string
    {
        $stream = $this->streamWithInput('', ...$arguments);
        $headers = '';
        $atLineStart = true;

        while (($chunk = fgets($stream, static::PATCH_CHUNK_BYTES)) !== false) {
            if ($atLineStart && preg_match('/^(diff --git |@@ |Binary files )/', $chunk) === 1) {
                $headers .= $chunk;
            }

            $atLineStart = str_ends_with($chunk, "\n");
        }

        fclose($stream);

        return $headers;
    }

    /**
     * Run a git command with the given input on stdin and get its output as a stream, which keeps output of any size out of memory.
     *
     * @return resource
     *
     * @throws RuntimeException
     */
    public function streamWithInput(string $input, string ...$arguments)
    {
        return Process::stream($this->directory, ['git', ...$arguments], $input, static::environment());
    }

    /**
     * Get the environment to run git in, which is the current one without the variables that change how a patch prints.
     *
     * @return array<string, string>
     */
    public static function environment(): array
    {
        return array_diff_key(getenv(), array_flip(static::DIFF_VARIABLES));
    }

    /**
     * Get the lines that a zero-context patch of one file adds or changes, counting a deletion at the line it leaves and the one after it, or every line up to the given count when the patch says the files differ without saying where.
     *
     * @return list<int>
     */
    public static function changedLines(string $patch, int $lineCount): array
    {
        preg_match_all('/^@@ -\S+ \+(\d+)(?:,(\d+))? @@/m', $patch, $hunks, PREG_SET_ORDER);

        if ($hunks === [] && preg_match('/^Binary files .* differ$/m', $patch) === 1) {
            return $lineCount === 0 ? [] : range(1, $lineCount);
        }

        $lines = [];

        foreach ($hunks as $hunk) {
            $start = (int) $hunk[1];
            $count = isset($hunk[2]) ? (int) $hunk[2] : 1;

            if ($count === 0) {
                array_push($lines, ...range(max($start, 1), $start + 1));

                continue;
            }

            for ($line = $start; $line < $start + $count; $line++) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * Read a raw diff and the patch of its copies, edits and renames into the diff of each file, by the path the change leaves it at, or the path of the file it deletes.
     *
     * @return array<string, Diff>
     *
     * @throws RuntimeException
     */
    public static function diffs(string $raw, string $patch): array
    {
        $entries = static::entries($raw);
        $records = [];

        for ($index = 0; $index < count($entries);) {
            [$previousMode, $mode, $previousBlob, $blob, $status] = explode(' ', substr($entries[$index++], 1));

            $previousPath = $entries[$index++];
            $path = in_array($status[0], ['C', 'R'], true) ? $entries[$index++] : $previousPath;

            $records[] = [
                'status' => $status[0],
                'path' => $path,
                'previousPath' => $previousPath,
                'blob' => in_array($mode, static::REGULAR_FILE_MODES, true) ? $blob : null,
                'previousBlob' => in_array($previousMode, static::REGULAR_FILE_MODES, true) ? $previousBlob : null,
            ];
        }

        $edits = array_filter($records, fn (array $record) => in_array($record['status'], static::EDITS, true));
        $blocks = array_combine(array_keys($edits), static::blocks($patch, count($edits)));
        $diffs = [];

        foreach ($records as $index => $record) {
            if ($record['status'] === static::UNMERGED || ($record['blob'] === null && $record['previousBlob'] === null)) {
                continue;
            }

            $key = $record['blob'] === null ? $record['previousPath'] : $record['path'];

            $diffs[$key] = [
                'path' => $record['path'],
                'previousPath' => $record['previousPath'],
                'blob' => $record['blob'],
                'previousBlob' => $record['previousBlob'],
                'patch' => $blocks[$index] ?? '',
            ];
        }

        return $diffs;
    }

    /**
     * Get the paths of the regular files a listing of the index (ls-files -s -z) or of a commit (ls-tree -r -z) holds, leaving out symbolic links, submodules and the paths a merge has not resolved.
     *
     * @return list<string>
     */
    public static function regularFiles(string $listing): array
    {
        $paths = [];

        foreach (static::entries($listing) as $entry) {
            [$details, $path] = explode("\t", $entry, 2);
            $fields = explode(' ', $details);

            if (in_array($fields[0], static::REGULAR_FILE_MODES, true) && ($fields[1] === 'blob' || $fields[2] === '0')) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Split a patch into the block of each file, in order, refusing a patch with another number of blocks than the raw diff calls for.
     *
     * @return list<string>
     *
     * @throws RuntimeException
     */
    public static function blocks(string $patch, int $count): array
    {
        $blocks = array_slice(preg_split('/^diff --git /m', $patch) ?: [], 1);

        if (count($blocks) !== $count) {
            throw new RuntimeException('git printed '.count($blocks).' of the '.$count.' patch blocks its raw diff calls for.');
        }

        return $blocks;
    }

    /**
     * Split NUL-separated output into its entries.
     *
     * @return list<string>
     */
    public static function entries(string $output): array
    {
        return array_values(array_filter(explode("\0", $output), fn (string $entry) => $entry !== ''));
    }
}
