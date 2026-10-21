<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Text;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\SyntaxError;
use ClaudioDekker\LaravelGuardrails\Violation;
use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;

/**
 * @api
 */
class FilesWithinTokenBudget extends Check
{
    /**
     * The most tokens a file may hold.
     */
    public const TOKEN_LIMIT = 5000;

    /**
     * The patterns of the names of the data files the check leaves out, which hold records, not code to read.
     */
    protected const DATA_FILES = ['*.svg', '*.sql', '*.dump', '*.csv', '*.min.js', '*.min.css'];

    /**
     * The bytes that count as one token.
     */
    public const BYTES_PER_TOKEN = 4;

    /**
     * Create the check with the most tokens a file may hold.
     */
    public function __construct(public readonly int $limit = self::TOKEN_LIMIT)
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The limit of FilesWithinTokenBudget must be at least 1 token.');
        }
    }

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return "Every file stays under {$this->limit} tokens, so an agent can read it whole.";
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        $bytes = $this->bytes();
        $perToken = static::BYTES_PER_TOKEN;

        return "An agent that cannot read a file whole works from the parts it did read. This flags any file over {$bytes} bytes, counted as {$this->limit} tokens of {$perToken} bytes each, whatever its type, except data files: SVG, SQL, dump and CSV files, and minified JavaScript and CSS. On a staged or branch run, a file already over the budget fails only when the change makes it larger, so editing it without growing it passes. Split the file by the job each part does, or move a large enum's case data into a table or a config file. Pass a limit to set another budget, as in new FilesWithinTokenBudget(limit: 8000).";
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        $tokens = $this->limit;
        $bytes = $this->bytes();

        return <<<MESSAGE
        Split the file into files under {$tokens} tokens ({$bytes} bytes) each.

        1. Group the file's contents by the one job each part does.
        2. Move each group into its own file, named after that job:
           - A PHP class: move a set of methods that belong together, and the properties only they use, into a new class, and call it from the original.
           - A test file: move each group of related tests into its own test file beside the original, with the setup those tests need.
           - A Markdown document: move each top-level section into its own page, and link to it from the original.
        3. Keep each new file under {$tokens} tokens, and keep the behaviour the same.

        Do not shrink a file by deleting docblocks, comments or blank lines, or by joining lines.
        MESSAGE;
    }

    /**
     * Get the patterns of the names of the files the check reads, which are every file.
     *
     * @return list<string>
     */
    protected function files(): array
    {
        return ['*'];
    }

    /**
     * Get the one-line description of the files the check reads.
     */
    public function scope(): string
    {
        return 'every file but data files';
    }

    /**
     * Determine if the check reads the file, which is any file but a data file.
     */
    protected function reads(File $file): bool
    {
        foreach (static::DATA_FILES as $pattern) {
            if (fnmatch($pattern, strtolower(basename($file->path)))) {
                return false;
            }
        }

        return parent::reads($file);
    }

    /**
     * Get a whole-file violation when the file holds more tokens than the limit, telling a single enum to move its cases' data out rather than to split.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        if ($this->tokens($file) <= $this->limit) {
            return [];
        }

        $message = $this->isSingleEnum($file) ? $this->enumMessage() : null;

        return [Violation::file($message)];
    }

    /**
     * Determine if the oversized file is new with the change, which is when the change makes it hold more tokens, wherever it moved.
     */
    protected function isNewWholeFile(Violation $violation, File $file, File $previous): bool
    {
        return $this->tokens($file) > $this->tokens($previous);
    }

    /**
     * Get the tokens the file holds, counting every started run of bytes as one.
     */
    protected function tokens(File $file): int
    {
        return intdiv(strlen($file->contents) + static::BYTES_PER_TOKEN - 1, static::BYTES_PER_TOKEN);
    }

    /**
     * Get the most bytes a file may hold, which is the limit in tokens of a fixed size.
     */
    protected function bytes(): int
    {
        return $this->limit * static::BYTES_PER_TOKEN;
    }

    /**
     * Get the failure message for a single enum, which is to move its cases' data out rather than to split the file.
     */
    protected function enumMessage(): string
    {
        $tokens = $this->limit;
        $bytes = $this->bytes();

        return "Move the data of the enum's cases out of the enum, for example into a database table or a config file, until the file is under {$tokens} tokens ({$bytes} bytes).";
    }

    /**
     * Determine if the file is PHP code that declares one named type, an enum.
     */
    protected function isSingleEnum(File $file): bool
    {
        if (! $file->isPhp()) {
            return false;
        }

        try {
            $declarations = $file->find(fn (Node $node) => $node instanceof ClassLike && $node->name !== null);
        } catch (SyntaxError) {
            return false;
        }

        return count($declarations) === 1 && $declarations[0] instanceof Enum_;
    }
}
