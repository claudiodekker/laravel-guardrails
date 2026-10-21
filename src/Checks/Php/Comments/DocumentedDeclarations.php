<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Comments;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;

/**
 * @api
 */
class DocumentedDeclarations extends Check
{
    /**
     * Create the check, which also asks for a docblock on a named class, interface, trait and enum when told to.
     */
    public function __construct(public readonly bool $classes = false)
    {
        //
    }

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return $this->classes
            ? 'Every class, method, function and property has a docblock.'
            : 'Every method, function and property has a docblock.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        $classes = $this->classes
            ? 'a class, interface, trait or enum with a name, and a method, function or property, in any of them'
            : 'a method, function or property, in an interface, trait or enum as much as in a class';

        $exempt = $this->classes ? 'Constants and closures' : 'Classes, constants and closures';
        $option = $this->classes ? '' : ' Pass classes: true to flag classes too.';

        return "A one-line docblock tells a reader, or an agent, what a declaration does without reading its body. This flags {$classes}, without one. {$exempt} need none, and a constructor's docblock covers its promoted properties. The members of an anonymous class need none. Test support code, such as the Pest file or a base test case, is read, and a *Test.php file in the tests is left to NoDocblocksInTests.{$option}";
    }

    /**
     * Get the one-line description of the files the check reads.
     */
    public function scope(): string
    {
        return 'PHP except *Test.php files in the tests';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        if ($this->classes) {
            return 'Add a docblock: one line saying what it does or holds, such as "Get the user\'s full name." or, on a class, "Hold the invoices of a customer."';
        }

        return 'Add a docblock: one line saying what it does or holds, such as "Get the user\'s full name."';
    }

    /**
     * Determine if the check reads the file, which is any PHP file but a test case.
     */
    protected function reads(File $file): bool
    {
        return parent::reads($file) && ! ($file->kind === Kind::TESTS && fnmatch('*Test.php', basename($file->path)));
    }

    /**
     * Get a violation at each method, function, property or, when asked for, class without a docblock, from its attributes through its name.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $anonymous = $this->anonymousMembers($file);
        $undocumented = array_filter($this->declarations($file), fn (Node $node) => $node->getDocComment() === null && ! in_array($node, $anonymous, true));

        return array_map(fn (ClassMethod|Function_|Property|ClassLike $node) => Violation::lines($node->getStartLine(), $this->nameLine($node)), array_values($undocumented));
    }

    /**
     * Get the methods, functions and properties of the file, and its named classes, interfaces, traits and enums when asked for.
     *
     * @return list<ClassMethod|Function_|Property|ClassLike>
     */
    protected function declarations(File $file): array
    {
        $declarations = [
            ...$file->findInstanceOf(ClassMethod::class),
            ...$file->findInstanceOf(Function_::class),
            ...$file->findInstanceOf(Property::class),
        ];

        if ($this->classes) {
            array_push($declarations, ...array_filter($file->findInstanceOf(ClassLike::class), fn (ClassLike $node) => $node->name !== null));
        }

        return $declarations;
    }

    /**
     * Get every node inside the body of an anonymous class in the file.
     *
     * @return list<Node>
     */
    protected function anonymousMembers(File $file): array
    {
        $bodies = array_map(fn (Class_ $class) => $class->stmts, array_filter($file->findInstanceOf(Class_::class), fn (Class_ $class) => $class->name === null));

        return array_values((new NodeFinder)->find(array_merge([], ...$bodies), fn () => true));
    }

    /**
     * Get the line that holds the declaration's name, past the attributes above it.
     */
    protected function nameLine(ClassMethod|Function_|Property|ClassLike $declaration): int
    {
        $name = $declaration instanceof Property ? $declaration->props[0]->name : $declaration->name;

        return ($name ?? $declaration)->getStartLine();
    }
}
