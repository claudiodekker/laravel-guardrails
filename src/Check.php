<?php

namespace ClaudioDekker\LaravelGuardrails;

use ClaudioDekker\LaravelGuardrails\Checks\Php\NoSyntaxErrors;
use InvalidArgumentException;
use LogicException;

/**
 * @api
 */
abstract class Check
{
    /**
     * The paths from the project root the check is limited to, which is none for the whole project.
     *
     * @var list<string>
     */
    private array $only = [];

    /**
     * The reason each allowed file or folder, by its path from the project root, may break the rule.
     *
     * @var array<string, string>
     */
    private array $allowed = [];

    /**
     * The name the check's failures print under, when guardrails.php gives one.
     */
    private ?string $label = null;

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    abstract public function description(): string;

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen, which is the description unless the check says more.
     */
    public function explanation(): string
    {
        return $this->description();
    }

    /**
     * Get the failure message, naming the fix, for every violation that gives none of its own, or null when each violation names its own.
     */
    public function message(): ?string
    {
        return null;
    }

    /**
     * Get the kinds of code the check reads, which is every kind unless the check says otherwise. Listing Kind::TEXT also reads every kind carved out of it later.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return Kind::cases();
    }

    /**
     * Get the one-line description of the files the check reads, which names their patterns and kinds unless the check says it better.
     */
    public function scope(): string
    {
        $patterns = $this->files();

        $files = match ($patterns) {
            ['*'] => 'every file',
            ['*.php'] => 'PHP',
            default => implode(', ', $patterns),
        };

        $kinds = Kind::describe($this->kinds());

        return $kinds === '' ? $files : "{$files} {$kinds}";
    }

    /**
     * Get the one-line description of the files the check reads, under the paths it is limited to when it has them.
     *
     * @final
     */
    public function coverage(): string
    {
        return $this->only === [] ? $this->scope() : $this->scope().', under '.implode(', ', $this->only);
    }

    /**
     * Limit the check to the files at or under the given paths from the project root, with a wildcard matching one folder name.
     *
     * @final
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function only(string $path, string ...$paths): static
    {
        if ($this instanceof NoSyntaxErrors) {
            throw new InvalidArgumentException('NoSyntaxErrors runs over every PHP file, so it takes no only(). Allow the files that are broken on purpose with allowing() instead.');
        }

        foreach ([$path, ...$paths] as $given) {
            $normalized = Layout::normalize($given);

            if ($normalized === '') {
                throw new InvalidArgumentException('only() takes paths from the project root, such as packages/*/src.');
            }

            $this->only[] = $normalized;
        }

        return $this;
    }

    /**
     * Leave the given files and folders alone, each by its path from the project root with the reason it may break the rule.
     *
     * @final
     *
     * @param  array<array-key, string>  $paths
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function allowing(array $paths): static
    {
        foreach ($paths as $path => $reason) {
            if (is_int($path) || trim($reason) === '') {
                throw new InvalidArgumentException("allowing() takes each path with the reason it may break the rule, as ['tests/Feature/LoginTest.php' => 'reason'].");
            }

            $normalized = Layout::normalize($path);

            if ($normalized === '') {
                throw new InvalidArgumentException("allowing() takes paths below the project root, because '{$path}' would turn the check off. Remove the check from guardrails.php instead.");
            }

            $this->allowed[$normalized] = $reason;
        }

        return $this;
    }

    /**
     * Get the files and folders the check leaves alone, each by its path from the project root.
     *
     * @final
     *
     * @return list<string>
     */
    public function allowances(): array
    {
        return array_keys($this->allowed);
    }

    /**
     * Print the check's failures under the given name instead of its class name.
     *
     * @final
     *
     * @return $this
     *
     * @throws InvalidArgumentException
     */
    public function as(string $name): static
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException("as() takes the name to print the check's failures under, such as ->as('NoCrmInBilling').");
        }

        $this->label = trim($name);

        return $this;
    }

    /**
     * Get the name as() gave the check to print its failures under, or null when it gave none.
     *
     * @final
     */
    public function label(): ?string
    {
        return $this->label;
    }

    /**
     * Get the violations the change brings into its file, each once, in line order with the ones at no line first, refusing one without a message.
     *
     * @internal
     *
     * @final
     *
     * @return list<Violation>
     *
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws SyntaxError
     */
    public function check(Change $change, Change ...$others): array
    {
        $kept = $this->keyed($this->judge($change));

        foreach ($others as $other) {
            if ($kept === []) {
                break;
            }

            $kept = array_intersect_key($kept, $this->keyed($this->judge($other)));
        }

        $violations = array_values($kept);

        usort($violations, fn (Violation $a, Violation $b) => ($a->line ?? 0) <=> ($b->line ?? 0));

        foreach ($violations as $violation) {
            $this->messageOf($violation);
        }

        return $violations;
    }

    /**
     * Refuse a pattern of the names of the files the check reads that names a folder, before the check reads any file.
     *
     * @internal
     *
     * @final
     *
     * @throws InvalidArgumentException
     */
    public function ensurePatterns(): void
    {
        $this->patterns();
    }

    /**
     * Get the message to print for the violation: its own, else the check's, refusing a violation that has neither.
     *
     * @internal
     *
     * @final
     *
     * @throws LogicException
     */
    public function messageOf(Violation $violation): string
    {
        $message = $violation->message ?? $this->message();

        if ($message === null) {
            $name = $this->label ?? static::name();

            throw new LogicException("{$name} gives no message. Return one from message(), or pass one to each Violation.");
        }

        return $message;
    }

    /**
     * Get the check's name for display, which is its full name without the namespace.
     *
     * @final
     */
    public static function name(): string
    {
        return basename(str_replace('\\', '/', static::fullName()));
    }

    /**
     * Get the check's class name, which for an anonymous class is the class it extends marked anonymous, without the file that declares it.
     *
     * @internal
     *
     * @final
     */
    public static function fullName(): string
    {
        return explode("\0", static::class, 2)[0];
    }

    /**
     * Get the patterns of the names of the files the check reads within its kinds, one of which must match. A pattern matches the file's name alone, never its folders.
     *
     * @return list<string>
     */
    protected function files(): array
    {
        return ['*.php'];
    }

    /**
     * Determine if the check reads the file, which is a file of one of its kinds whose name matches one of its patterns, unless the check narrows it further. Generated and binary files, lockfiles and vendor folders are skipped before this is asked.
     *
     * @throws InvalidArgumentException
     */
    protected function reads(File $file): bool
    {
        if (! $this->readsKind($file->kind)) {
            return false;
        }

        foreach ($this->patterns() as $pattern) {
            if (fnmatch($pattern, basename($file->path))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the check lists the kind, or a kind that covers it.
     */
    private function readsKind(Kind $kind): bool
    {
        foreach ($this->kinds() as $listed) {
            if ($listed->includes($kind)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the patterns of the names of the files the check reads, refusing one that names a folder.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    private function patterns(): array
    {
        $patterns = $this->files();

        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '/')) {
                throw new InvalidArgumentException(static::fullName().": files() takes patterns on the file's name, such as *.php. {$pattern} names a folder: declare the kinds with kinds() and limit the paths with only() in guardrails.php.");
            }
        }

        return $patterns;
    }

    /**
     * Get the violations of the rule in the file.
     *
     * @return list<Violation>
     */
    abstract protected function violations(File $file): array;

    /**
     * Get the violations of the rule in the change, which are those of the file it leaves, and none for a change that deletes the file.
     *
     * @return list<Violation>
     */
    protected function violationsOf(Change $change): array
    {
        return $change->isDeleted() ? [] : $this->violations($change->file);
    }

    /**
     * Determine if the whole-file violation is new with a change from the previous file, which it is unless the previous file had a whole-file violation with the same message.
     */
    protected function isNewWholeFile(Violation $violation, File $file, File $previous): bool
    {
        foreach ($this->violations($previous) as $old) {
            if ($old->isWholeFile() && $this->messageOf($old) === $this->messageOf($violation)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the violations the change brings into its file, or none when the file does not parse.
     *
     * @return list<Violation>
     *
     * @throws InvalidArgumentException
     * @throws SyntaxError
     */
    private function judge(Change $change): array
    {
        try {
            return $this->newIn($change);
        } catch (SyntaxError $error) {
            $about = $change->isDeleted() ? $change->previous : $change->file;

            if (! $error->isFrom($about)) {
                throw $error;
            }

            return [];
        }
    }

    /**
     * Get the violations the change brings into its file if the check reads the file, or every violation of a change that deletes a file it reads.
     *
     * @return list<Violation>
     *
     * @throws InvalidArgumentException
     * @throws SyntaxError
     */
    private function newIn(Change $change): array
    {
        if ($change->isDeleted()) {
            return $this->applies($change->previous) ? $this->violationsOf($change) : [];
        }

        if (! $this->applies($change->file)) {
            return $this->movedOut($change);
        }

        try {
            $violations = $this->violationsOf($change);
        } catch (SyntaxError $error) {
            if ($change->isAdded() || ! $error->isFrom($change->previous)) {
                throw $error;
            }

            return $this->violationsOf(Change::added($change->file));
        }

        if ($change->isAdded() || ! $this->appliesToPrevious($change->previous)) {
            return $violations;
        }

        $new = fn (Violation $violation) => $violation->isWholeFile()
            ? $this->isNewAgainst($violation, $change->file, $change->previous)
            : $change->touches($violation);

        return array_values(array_filter($violations, $new));
    }

    /**
     * Get the violations of the deletion of the file before a change that moves it out of the files the check reads, or none when the change does not.
     *
     * @return list<Violation>
     *
     * @throws InvalidArgumentException
     * @throws SyntaxError
     */
    private function movedOut(Change $change): array
    {
        if ($change->isAdded() || ! $this->appliesToPrevious($change->previous)) {
            return [];
        }

        try {
            return $this->violationsOf(Change::deleted($change->previous));
        } catch (SyntaxError $error) {
            if (! $error->isFrom($change->previous)) {
                throw $error;
            }

            return [];
        }
    }

    /**
     * Determine if the check applies to the file before the change, which it does not when deciding needs that file's code and it does not parse, so every violation counts.
     *
     * @throws InvalidArgumentException
     * @throws SyntaxError
     */
    private function appliesToPrevious(File $previous): bool
    {
        try {
            return $this->applies($previous);
        } catch (SyntaxError $error) {
            if (! $error->isFrom($previous)) {
                throw $error;
            }

            return false;
        }
    }

    /**
     * Determine if the whole-file violation is new against the previous file, as isNewWholeFile() decides, or when it cannot tell because that file does not parse.
     *
     * @throws SyntaxError
     */
    private function isNewAgainst(Violation $violation, File $file, File $previous): bool
    {
        try {
            return $this->isNewWholeFile($violation, $file, $previous);
        } catch (SyntaxError $error) {
            if (! $error->isFrom($previous)) {
                throw $error;
            }

            return true;
        }
    }

    /**
     * Determine if the check applies to the file: one no check skips, within the paths it covers, that it reads.
     */
    private function applies(File $file): bool
    {
        return ! File::isSkippedPath($file->path)
            && ! File::isBinary($file->contents)
            && $this->covers($file->path)
            && $this->reads($file);
    }

    /**
     * Determine if the path is within the paths the check is limited to and outside the ones it allows.
     */
    private function covers(string $path): bool
    {
        foreach (array_keys($this->allowed) as $allowed) {
            if (Layout::holds($allowed, $path)) {
                return false;
            }
        }

        if ($this->only === []) {
            return true;
        }

        foreach ($this->only as $root) {
            if (Layout::holds($root, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Key the violations by their line, the lines they are judged by and their message, keeping the first of each.
     *
     * @param  list<Violation>  $violations
     * @return array<string, Violation>
     */
    private function keyed(array $violations): array
    {
        $keyed = [];

        foreach ($violations as $violation) {
            $keyed["{$violation->line}:".json_encode($violation->ranges).":{$violation->message}"] ??= $violation;
        }

        return $keyed;
    }
}
