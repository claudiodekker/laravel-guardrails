<?php

namespace ClaudioDekker\LaravelGuardrails;

use InvalidArgumentException;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\Token;

/**
 * @internal
 */
class ConfigurationFile
{
    /**
     * The name of the file, at the root of the project.
     */
    public const NAME = 'guardrails.php';

    /**
     * Create a handle on the file at the given path from its source, its statements and their tokens.
     *
     * @param  array<Stmt>  $statements
     * @param  array<Token>  $tokens
     *
     * @throws InvalidArgumentException
     */
    protected function __construct(public readonly string $path, protected string $source, protected array $statements, protected array $tokens)
    {
        $this->returned($statements);
    }

    /**
     * Read the guardrails.php of the project in the given directory, or start one when it does not exist.
     *
     * @throws InvalidArgumentException
     */
    public static function read(string $directory): self
    {
        $path = $directory.'/'.self::NAME;

        return self::parse($path, (string) file_get_contents(is_file($path) ? $path : dirname(__DIR__).'/stubs/guardrails.stub'));
    }

    /**
     * Parse the source of the file at the given path.
     *
     * @throws InvalidArgumentException
     */
    protected static function parse(string $path, string $source): self
    {
        $parser = (new ParserFactory)->createForHostVersion();

        try {
            $statements = $parser->parse($source) ?? [];

            (new NodeTraverser(new NameResolver(options: ['replaceNodes' => false])))->traverse($statements);
        } catch (Error $error) {
            throw new InvalidArgumentException("{$path} does not parse: {$error->getRawMessage()}");
        }

        return new self($path, $source, $statements, $parser->getTokens());
    }

    /**
     * Get the class of every class name and instance entry, in file order.
     *
     * @return list<string>
     */
    public function classes(): array
    {
        return $this->classesOf($this->array($this->statements)->items);
    }

    /**
     * Get the class of every instance entry, in file order.
     *
     * @return list<string>
     */
    public function configured(): array
    {
        $instances = array_filter($this->array($this->statements)->items, fn (ArrayItem $item) => $this->instance($item->value) !== null);

        return $this->classesOf($instances);
    }

    /**
     * Write the file with the given classes selected: hand-written entries stay, entries of other classes go, and each class without an entry gets one.
     *
     * @param  list<string>  $classes
     */
    public function select(array $classes): void
    {
        $file = $this;

        foreach (array_diff($classes, $this->classes()) as $class) {
            $file = $file->importing($class);
        }

        file_put_contents($this->path, $file->rewriting($classes)->source);
    }

    /**
     * Get the file with a plain use statement for the class in alphabetical position, unless one names it or another takes its short name.
     */
    protected function importing(string $class): self
    {
        if ($this->alias($class) !== null || $this->isTaken($this->shortName($class))) {
            return $this;
        }

        $imports = $this->imports();
        $preceding = array_filter($imports, fn (Use_ $import) => strcmp($import->uses[0]->name->toString(), $class) < 0);

        if ($preceding !== []) {
            $anchor = end($preceding);

            return $this->inserting("\nuse {$class};", $anchor->getEndFilePos() + 1);
        }

        if ($imports !== []) {
            return $this->inserting("use {$class};\n", $imports[0]->getStartFilePos());
        }

        return $this->inserting("use {$class};\n\n", $this->header()->end());
    }

    /**
     * Get the header above the first statement that is not a declare.
     */
    protected function header(): Header
    {
        return new Header($this->firstStatementAfterDeclares(), $this->source);
    }

    /**
     * Get the file with the header put back above its first statement when printing the file dropped it.
     */
    protected function restoring(string $header): self
    {
        if ($header === '' || str_contains($this->source, $header)) {
            return $this;
        }

        return $this->inserting($header, $this->header()->end());
    }

    /**
     * Get the offset where the node starts in the source, comments above it included.
     */
    protected function start(Node $node): int
    {
        $comments = $node->getComments();

        return $comments === [] ? $node->getStartFilePos() : $comments[0]->getStartFilePos();
    }

    /**
     * Get the file with the text inserted at the given offset of its source.
     */
    protected function inserting(string $text, int $offset): self
    {
        return self::parse($this->path, substr_replace($this->source, $text, $offset, 0));
    }

    /**
     * Get the first statement that is not a declare, before which an import block belongs.
     */
    protected function firstStatementAfterDeclares(): Stmt
    {
        foreach ($this->statements as $statement) {
            if (! $statement instanceof Declare_) {
                return $statement;
            }
        }

        return $this->returned($this->statements);
    }

    /**
     * Get the file with the given classes selected: the entries of other classes dropped, with their imports, and the entries the file lacks added.
     *
     * @param  list<string>  $classes
     */
    protected function rewriting(array $classes): self
    {
        $statements = (new NodeTraverser(new CloningVisitor))->traverse($this->statements);
        $return = $this->returned($statements);
        $array = $this->array($statements);
        $kept = array_values(array_filter($array->items, fn (ArrayItem $item) => $this->isKept($item, $classes)));
        $items = $this->placed($kept, $classes);
        $new = array_values(array_diff($classes, $this->classes()));
        $prepended = $this->isOneLine($array) ? [] : array_slice($new, 0, $this->leading($items, $kept));
        $appended = $this->isOneLine($array) ? [] : array_slice($new, count($new) - $this->trailing($items, $kept));

        if ($this->isOneLine($array) && $items !== $kept) {
            $return->expr = new Array_($items, ['kind' => Array_::KIND_SHORT]);
        } else {
            $array->items = array_slice($items, count($prepended), count($items) - count($appended) - count($prepended));
        }

        $statements = $this->withoutImportsOf($statements, array_values(array_diff($this->classes(), $classes)));
        $source = (new Printer)->printFormatPreserving($statements, $this->statements, $this->tokens);

        return self::parse($this->path, $source)->restoring($this->header()->text())->prepending($prepended)->appending($appended);
    }

    /**
     * Get the file with an entry of each class added as text on the lines before the first entry and the comments above it, so those comments stay with that entry.
     *
     * @param  list<string>  $classes
     */
    protected function prepending(array $classes): self
    {
        if ($classes === []) {
            return $this;
        }

        $array = $this->array($this->statements);
        $indent = $this->indentation($array);
        $entries = implode('', array_map(fn (string $class) => "{$indent}{$this->entryText($class)},\n", $classes));
        $offset = (int) strrpos(substr($this->source, 0, $this->start($array->items[0])), "\n") + 1;

        return $this->inserting($entries, $offset);
    }

    /**
     * Get the file with an entry of each class added as text on the lines before the closing bracket, so a comment after the last entry stays where it is.
     *
     * @param  list<string>  $classes
     */
    protected function appending(array $classes): self
    {
        if ($classes === []) {
            return $this;
        }

        $array = $this->array($this->statements);
        $indent = $this->indentation($array);
        $entries = implode('', array_map(fn (string $class) => "{$indent}{$this->entryText($class)},\n", $classes));
        $start = $array->getStartFilePos();
        $inside = substr($this->source, $start + 1, $array->getEndFilePos() - $start - 1);

        if (trim($inside) === '') {
            $opening = (int) strrpos(substr($this->source, 0, $start), "\n") + 1;
            $closing = preg_replace('/\S.*$/s', '', substr($this->source, $opening, $start - $opening));

            return self::parse($this->path, substr_replace($this->source, "\n{$entries}{$closing}", $start + 1, strlen($inside)));
        }

        $line = (int) strrpos(substr($this->source, 0, $array->getEndFilePos()), "\n") + 1;
        $offset = trim(substr($this->source, $line, $array->getEndFilePos() - $line)) === '' ? $line : $array->getEndFilePos();

        return $this->inserting($entries, $offset);
    }

    /**
     * Determine if the array literal sits on one line.
     */
    protected function isOneLine(Array_ $array): bool
    {
        return ! str_contains(substr($this->source, $array->getStartFilePos(), $array->getEndFilePos() - $array->getStartFilePos()), "\n");
    }

    /**
     * Get the number of items before the first kept one, which are the new entries that go first, or none when nothing is kept.
     *
     * @param  list<ArrayItem>  $items
     * @param  list<ArrayItem>  $kept
     */
    protected function leading(array $items, array $kept): int
    {
        return $kept === [] ? 0 : (int) array_search($kept[0], $items, true);
    }

    /**
     * Get the number of items after the last kept one, which are the new entries that go last.
     *
     * @param  list<ArrayItem>  $items
     * @param  list<ArrayItem>  $kept
     */
    protected function trailing(array $items, array $kept): int
    {
        $last = $kept === [] ? -1 : (int) array_search(end($kept), $items, true);

        return count($items) - $last - 1;
    }

    /**
     * Get the whitespace the entries of the array are indented with: that of its first entry, or one level past its closing bracket.
     */
    protected function indentation(Array_ $array): string
    {
        $first = $array->items[0] ?? null;
        $offset = $first === null ? $array->getEndFilePos() : $first->getStartFilePos();
        $start = (int) strrpos(substr($this->source, 0, $offset), "\n") + 1;
        $line = substr($this->source, $start, $offset - $start);
        $indent = substr($line, 0, strlen($line) - strlen(ltrim($line)));

        return $first === null ? $indent.'    ' : $indent;
    }

    /**
     * Determine if the entry stays: a hand-written one always does, and one of a class does when the class is selected.
     *
     * @param  list<string>  $classes
     */
    protected function isKept(ArrayItem $item, array $classes): bool
    {
        $class = $this->classOf($item);

        return $class === null || in_array($class, $classes, true);
    }

    /**
     * Get the entries with one added for each class they lack, each in its place among the entries of the given classes.
     *
     * @param  list<ArrayItem>  $items
     * @param  list<string>  $classes
     * @return list<ArrayItem>
     */
    protected function placed(array $items, array $classes): array
    {
        $present = array_map(fn (ArrayItem $item) => $this->classOf($item), $items);

        foreach ($classes as $class) {
            if (in_array($class, $present, true)) {
                continue;
            }

            $position = Placement::of($present, $classes, $class);

            array_splice($items, $position, 0, [$this->entry($class)]);
            array_splice($present, $position, 0, [$class]);
        }

        return $items;
    }

    /**
     * Get an entry of the class by the alias its import gives it, or by its full name when no import names it.
     */
    protected function entry(string $class): ArrayItem
    {
        $alias = $this->alias($class);
        $name = $alias === null ? new Name\FullyQualified($class) : new Name($alias);

        return new ArrayItem(new ClassConstFetch($name, 'class'));
    }

    /**
     * Get the text of an entry of the class, as the entry itself is written.
     */
    protected function entryText(string $class): string
    {
        $alias = $this->alias($class);

        return ($alias ?? '\\'.$class).'::class';
    }

    /**
     * Get the statements without the plain imports of the given classes that nothing outside the imports refers to any more.
     *
     * @param  array<Node>  $statements
     * @param  list<string>  $classes
     * @return list<Node>
     */
    protected function withoutImportsOf(array $statements, array $classes): array
    {
        $others = array_filter($statements, fn (Node $statement) => ! $statement instanceof Use_);
        $names = (new NodeFinder)->findInstanceOf($others, Name::class);
        $referenced = array_map(fn (Name $name) => $name->getFirst(), array_filter($names, fn (Name $name) => ! $name instanceof Name\FullyQualified));
        $unused = fn (UseItem $use) => in_array($use->name->toString(), $classes, true) && ! in_array($use->getAlias()->toString(), $referenced, true);

        foreach ($statements as $index => $statement) {
            if (! $this->isImport($statement)) {
                continue;
            }

            $statement->uses = array_values(array_filter($statement->uses, fn (UseItem $use) => ! $unused($use)));

            if ($statement->uses === []) {
                unset($statements[$index]);
            }
        }

        return array_values($statements);
    }

    /**
     * Get the plain use statements of the file, in file order.
     *
     * @return list<Use_>
     */
    protected function imports(): array
    {
        return array_values(array_filter($this->statements, fn (Stmt $statement) => $this->isImport($statement)));
    }

    /**
     * Determine if the statement is a plain use statement, which imports classes.
     *
     * @phpstan-assert-if-true Use_ $statement
     */
    protected function isImport(Node $statement): bool
    {
        return $statement instanceof Use_ && $statement->type === Use_::TYPE_NORMAL;
    }

    /**
     * Get the alias an import gives the class, if one names it.
     */
    protected function alias(string $class): ?string
    {
        $alias = array_search($class, $this->aliases(), true);

        return $alias === false ? null : $alias;
    }

    /**
     * Determine if an import already uses the given alias.
     */
    protected function isTaken(string $alias): bool
    {
        return array_key_exists($alias, $this->aliases());
    }

    /**
     * Get the class each plain or group import names, by the alias it gives it.
     *
     * @return array<string, string>
     */
    protected function aliases(): array
    {
        $aliases = [];

        foreach ($this->statements as $statement) {
            if (! $statement instanceof Use_ && ! $statement instanceof GroupUse) {
                continue;
            }

            $prefix = $statement instanceof GroupUse ? $statement->prefix->toString().'\\' : '';

            foreach ($statement->uses as $use) {
                if (($use->type === Use_::TYPE_UNKNOWN ? $statement->type : $use->type) === Use_::TYPE_NORMAL) {
                    $aliases[$use->getAlias()->toString()] = $prefix.$use->name->toString();
                }
            }
        }

        return $aliases;
    }

    /**
     * Get the class name without its namespace.
     */
    protected function shortName(string $class): string
    {
        return basename(str_replace('\\', '/', $class));
    }

    /**
     * Get the classes of the entries that are a class name or an instance, in order.
     *
     * @param  array<ArrayItem>  $items
     * @return list<string>
     */
    protected function classesOf(array $items): array
    {
        return array_values(array_filter(array_map(fn (ArrayItem $item) => $this->classOf($item), $items)));
    }

    /**
     * Get the class of the entry, which is null for a hand-written one: anything but a class name, quoted or as a constant, or a new instance of a named class.
     */
    protected function classOf(ArrayItem $item): ?string
    {
        if ($item->unpack || $item->key !== null) {
            return null;
        }

        if ($item->value instanceof String_) {
            return ltrim($item->value->value, '\\');
        }

        $name = $this->named($item->value);

        if (! $name instanceof Name) {
            return null;
        }

        $resolved = $name->getAttribute('resolvedName');

        return $resolved instanceof Name ? $resolved->toString() : $name->toString();
    }

    /**
     * Get what the entry names: the class of a "class" constant fetch or of a new instance, with or without calls chained on it.
     */
    protected function named(Expr $value): ?Node
    {
        $instance = $this->instance($value);

        if ($instance !== null) {
            return $instance->class;
        }

        if (
            $value instanceof ClassConstFetch
            && $value->name instanceof Identifier
            && $value->name->toLowerString() === 'class'
        ) {
            return $value->class;
        }

        return null;
    }

    /**
     * Get the new instance the entry is, or the one the calls chained on it start from.
     */
    protected function instance(Expr $value): ?New_
    {
        while ($value instanceof MethodCall || $value instanceof NullsafeMethodCall) {
            $value = $value->var;
        }

        return $value instanceof New_ ? $value : null;
    }

    /**
     * Get the array literal the statements return.
     *
     * @param  array<Node>  $statements
     *
     * @throws InvalidArgumentException
     */
    protected function array(array $statements): Array_
    {
        $array = $this->returned($statements)->expr;

        assert($array instanceof Array_);

        return $array;
    }

    /**
     * Get the statement that returns the array literal, which the file must hold for the command to edit it.
     *
     * @param  array<Node>  $statements
     *
     * @throws InvalidArgumentException
     */
    protected function returned(array $statements): Return_
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Return_ && $statement->expr instanceof Array_) {
                return $statement;
            }
        }

        throw new InvalidArgumentException("{$this->path} must return a list for guardrails install to edit it. Edit the selection by hand.");
    }
}
