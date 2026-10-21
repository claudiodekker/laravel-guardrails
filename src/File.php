<?php

namespace ClaudioDekker\LaravelGuardrails;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PhpParser\Token;

/**
 * @api
 */
class File
{
    /**
     * The number of leading bytes git inspects to tell a binary file from text.
     *
     * @internal
     */
    public const BINARY_PROBE_BYTES = 8000;

    /**
     * The names of the lockfiles package managers write, which no check reads.
     */
    protected const LOCKFILES = ['bun.lock', 'bun.lockb', 'composer.lock', 'package-lock.json', 'pnpm-lock.yaml', 'yarn.lock'];

    /**
     * The file's statements, with names resolved.
     *
     * @var array<Node>|null
     */
    protected ?array $statements = null;

    /**
     * The file's tokens.
     *
     * @var array<Token>
     */
    protected array $tokens = [];

    /**
     * The error the file's code does not parse with, once a parse failed.
     */
    protected ?SyntaxError $syntaxError = null;

    /**
     * Create a file from its path, its contents, the kind of code it holds and the project it comes from, as the same revision holds it.
     *
     * @internal
     */
    public function __construct(
        public readonly string $path,
        public readonly string $contents,
        public readonly Kind $kind,
        public readonly Project $project,
    ) {
        //
    }

    /**
     * Determine if every check skips the file at the path by its name: a lockfile, or a file under a vendor folder.
     *
     * @internal
     */
    public static function isSkippedPath(string $path): bool
    {
        return in_array(basename($path), static::LOCKFILES, true)
            || in_array('vendor', explode('/', dirname($path)), true);
    }

    /**
     * Determine if the first bytes of a file show it holds binary data, which every check skips.
     *
     * @internal
     */
    public static function isBinary(string $head): bool
    {
        return str_contains(substr($head, 0, static::BINARY_PROBE_BYTES), "\0");
    }

    /**
     * Determine if the file sits in a folder of the given name, at any depth.
     */
    public function isIn(string $folder): bool
    {
        return in_array($folder, explode('/', dirname($this->path)), true);
    }

    /**
     * Get the number of lines the file has, which a newline at the end does not add to.
     */
    public function lineCount(): int
    {
        if ($this->contents === '') {
            return 0;
        }

        return substr_count($this->contents, "\n") + (str_ends_with($this->contents, "\n") ? 0 : 1);
    }

    /**
     * Determine if the file is a Blade template, which is markup rather than PHP code.
     */
    public function isBladeTemplate(): bool
    {
        return str_ends_with($this->path, '.blade.php');
    }

    /**
     * Determine if the file holds PHP code, which a Blade template and a template in a stubs folder do not.
     */
    public function isPhp(): bool
    {
        return str_ends_with($this->path, '.php')
            && ! $this->isBladeTemplate()
            && ! $this->isIn('stubs');
    }

    /**
     * Get the file's statements, with every class and function name resolved, or none for a file that is not PHP code.
     *
     * @return array<Node>
     *
     * @throws SyntaxError
     */
    public function statements(): array
    {
        if ($this->syntaxError !== null) {
            throw $this->syntaxError;
        }

        return $this->statements ??= $this->isPhp() ? $this->parse() : [];
    }

    /**
     * Get the file's tokens.
     *
     * @return array<Token>
     */
    public function tokens(): array
    {
        $this->statements();

        return $this->tokens;
    }

    /**
     * Find the nodes the given test accepts.
     *
     * @param  callable(Node): bool  $test
     * @return list<Node>
     */
    public function find(callable $test): array
    {
        return array_values((new NodeFinder)->find($this->statements(), $test));
    }

    /**
     * Find the nodes of the given class.
     *
     * @template TNode of Node
     *
     * @param  class-string<TNode>  $class
     * @return list<TNode>
     */
    public function findInstanceOf(string $class): array
    {
        return array_values((new NodeFinder)->findInstanceOf($this->statements(), $class));
    }

    /**
     * Parse the file into statements for the PHP version the project targets, or the running one, keeping its tokens, with every class and function name resolved.
     *
     * @return array<Node>
     *
     * @throws SyntaxError
     */
    protected function parse(): array
    {
        $parser = (new ParserFactory)->createForVersion($this->project->layout()->php() ?? PhpVersion::getHostVersion());

        try {
            $parsed = $parser->parse($this->contents) ?? [];
            $statements = (new NodeTraverser(new NameResolver))->traverse($parsed);
        } catch (Error $error) {
            $this->syntaxError = new SyntaxError($error->getRawMessage().'. Fix it so the file parses, then run the checks again.', $error->getStartLine(), $this);

            throw $this->syntaxError;
        }

        $this->tokens = $parser->getTokens();

        return $statements;
    }
}
