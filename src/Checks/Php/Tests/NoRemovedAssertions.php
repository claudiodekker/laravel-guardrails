<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Tests;

use ClaudioDekker\LaravelGuardrails\Change;
use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;

/**
 * @api
 */
class NoRemovedAssertions extends Check
{
    /**
     * The name PHPUnit's Test attribute resolves to.
     */
    protected const TEST_ATTRIBUTE = 'phpunit\framework\attributes\test';

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'A test that stays does not lose assertions.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'Stripping the assertions out of a test makes it pass without checking anything. This counts the assertions in each test of a file ending in Test.php, which are calls to methods named assert*, Pest\'s expect() and its and() chain, and flags a test that has fewer after the change than before. A PHPUnit test is a test* method, or one marked #[Test] or @test, and a Pest test is an it() or test() call, each found by its name. A deleted, renamed or new test passes, because the behavior may be gone. Restore the assertion, or assert it another way.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Restore the assertions this test lost, or assert the same behavior another way, because a test with fewer assertions checks less.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return [Kind::TESTS];
    }

    /**
     * Get the patterns of the names of the files the check reads, which are the tests.
     *
     * @return list<string>
     */
    protected function files(): array
    {
        return ['*Test.php'];
    }

    /**
     * Get no violations of the file alone, because the rule compares each test with the test before.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return [];
    }

    /**
     * Get a violation at each test that has fewer assertions than it had before, which a new, deleted or renamed test cannot have.
     *
     * @return list<Violation>
     */
    protected function violationsOf(Change $change): array
    {
        if ($change->isAdded() || $change->isDeleted()) {
            return [];
        }

        $before = $this->testsIn($change->previous);
        $violations = [];

        foreach ($this->testsIn($change->file) as $identity => $test) {
            if (isset($before[$identity]) && $test['assertions'] < $before[$identity]['assertions']) {
                $violations[] = Violation::at($test['node']);
            }
        }

        return $violations;
    }

    /**
     * Get the tests of the file by their identity, each with its number of assertions and the node to report it at.
     *
     * @return array<string, array{assertions: int, node: Node}>
     */
    protected function testsIn(File $file): array
    {
        return [...$this->phpUnitTestsIn($file), ...$this->pestTestsIn($file->statements())];
    }

    /**
     * Get the test methods of the file's classes by Class::method.
     *
     * @return array<string, array{assertions: int, node: Node}>
     */
    protected function phpUnitTestsIn(File $file): array
    {
        $tests = [];

        foreach ($file->findInstanceOf(ClassLike::class) as $class) {
            if ($class->name === null) {
                continue;
            }

            foreach ($class->getMethods() as $method) {
                if ($this->isPhpUnitTest($method)) {
                    $tests["{$class->namespacedName}::{$method->name}"] = ['assertions' => $this->assertionsIn($method), 'node' => $method];
                }
            }
        }

        return $tests;
    }

    /**
     * Determine if the method is a PHPUnit test, which is named test*, or carries the Test attribute or an @test tag.
     */
    protected function isPhpUnitTest(ClassMethod $method): bool
    {
        if (str_starts_with($method->name->toString(), 'test')) {
            return true;
        }

        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->toLowerString() === static::TEST_ATTRIBUTE) {
                    return true;
                }
            }
        }

        return preg_match('/@test(?![\w-])/', $method->getDocComment()?->getText() ?? '') === 1;
    }

    /**
     * Get the Pest tests among the statements by their description, behind the descriptions of the describe() blocks around them.
     *
     * @param  array<Node>  $statements
     * @param  list<string>  $blocks
     * @return array<string, array{assertions: int, node: Node}>
     */
    protected function pestTestsIn(array $statements, array $blocks = []): array
    {
        $tests = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Namespace_) {
                $tests = [...$tests, ...$this->pestTestsIn($statement->stmts, $blocks)];

                continue;
            }

            $call = $statement instanceof Expression ? $this->pestCallOf($statement->expr) : null;
            $description = $call === null ? null : $this->argumentOf($call, 0);

            if (
                $call === null
                || ! $description instanceof String_
                || ! $call->name instanceof Name
            ) {
                continue;
            }

            $function = $call->name->toLowerString();

            if ($function === 'describe') {
                $tests = [...$tests, ...$this->pestTestsIn($this->bodyOf($call), [...$blocks, $description->value])];
            } elseif ($function === 'it' || $function === 'test') {
                $name = $function === 'it' ? "it {$description->value}" : $description->value;
                $tests[implode(' ', [...$blocks, $name])] = ['assertions' => $this->assertionsIn($statement), 'node' => $statement];
            }
        }

        return $tests;
    }

    /**
     * Get the Pest function call a chain of method calls starts at, such as the it() in it('works')->with([1, 2]), or null for any other expression.
     */
    protected function pestCallOf(Expr $expression): ?FuncCall
    {
        while ($expression instanceof MethodCall) {
            $expression = $expression->var;
        }

        return $expression instanceof FuncCall ? $expression : null;
    }

    /**
     * Get the statements of the closure a describe() call runs.
     *
     * @return array<Node>
     */
    protected function bodyOf(FuncCall $describe): array
    {
        $closure = $this->argumentOf($describe, 1);

        return $closure instanceof Closure ? $closure->stmts : [];
    }

    /**
     * Get the expression passed as the argument at the position, or null when the call passes none there.
     */
    protected function argumentOf(FuncCall $call, int $position): ?Expr
    {
        $argument = $call->args[$position] ?? null;

        return $argument instanceof Arg ? $argument->value : null;
    }

    /**
     * Get the number of assertions under the node: calls to methods whose name starts with assert, expect() and the and() of a Pest chain.
     */
    protected function assertionsIn(Node $test): int
    {
        return count((new NodeFinder)->find($test, $this->isAssertion(...)));
    }

    /**
     * Determine if the node asserts something.
     */
    protected function isAssertion(Node $node): bool
    {
        if ($node instanceof MethodCall || $node instanceof StaticCall) {
            return $node->name instanceof Identifier
                && (str_starts_with($node->name->toLowerString(), 'assert') || ($node instanceof MethodCall && $node->name->toLowerString() === 'and'));
        }

        return $node instanceof FuncCall
            && $node->name instanceof Name
            && $node->name->toLowerString() === 'expect';
    }
}
