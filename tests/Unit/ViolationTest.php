<?php

use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Expression;

it('reports a node at the line of its name and is brought in by a change to any of its lines', function () {
    $calls = nodesOf("<?php\n\nconfig(\n    'app.limit',\n    5,\n);\n", FuncCall::class);

    $violation = Violation::at($calls[0], 'Remove the default.');

    expect($violation->line)->toBe(3)
        ->and($violation->ranges)->toBe([[3, 6]])
        ->and($violation->message)->toBe('Remove the default.')
        ->and($violation->isWholeFile())->toBeFalse()
        ->and($violation->touches([5 => true]))->toBeTrue()
        ->and($violation->touches([2 => true, 7 => true]))->toBeFalse();
});

it('reports a call on a chain at the line of its name', function () {
    $calls = nodesOf("<?php\n\n\$q\n    ->where(1)\n    ->dd();\n", MethodCall::class);

    $violation = Violation::at($calls[0]->name);

    expect($violation->line)->toBe(5)
        ->and($violation->ranges)->toBe([[5, 5]])
        ->and($violation->message)->toBeNull();
});

it('reports a class with an attribute above it at the line of its name, and is brought in by a change to the attribute', function () {
    $classes = nodesOf("<?php\n\n#[Attribute]\nclass Example\n{\n}\n", Class_::class);

    $violation = Violation::at($classes[0]);

    expect($violation->line)->toBe(4)
        ->and($violation->ranges)->toBe([[3, 6]]);
});

it('reports a node without a name at its first line', function () {
    $evals = nodesOf("<?php\n\neval(\n    \$code\n);\n", Eval_::class);

    $violation = Violation::at($evals[0]);

    expect($violation->line)->toBe(3)
        ->and($violation->ranges)->toBe([[3, 5]]);
});

it('leaves the comment above a node out of the lines it is judged by', function () {
    $classes = nodesOf("<?php\n\n/**\n * Hold it.\n */\nclass Example\n{\n}\n", Class_::class);

    $violation = Violation::at($classes[0]);

    expect($violation->line)->toBe(6)
        ->and($violation->ranges)->toBe([[6, 8]]);
});

it('holds one line', function () {
    $violation = Violation::line(7, 'Reword it.');

    expect($violation->line)->toBe(7)
        ->and($violation->ranges)->toBe([[7, 7]])
        ->and($violation->message)->toBe('Reword it.');
});

it('holds a run of lines, reported at the first', function () {
    $violation = Violation::lines(3, 6, 'Reword it.');

    expect($violation->line)->toBe(3)
        ->and($violation->ranges)->toBe([[3, 6]])
        ->and($violation->message)->toBe('Reword it.')
        ->and($violation->touches([6 => true]))->toBeTrue()
        ->and($violation->touches([7 => true]))->toBeFalse();
});

it('refuses a run of lines whose last line precedes its first', function () {
    expect(fn () => Violation::lines(6, 3))
        ->toThrow(InvalidArgumentException::class, 'Violation::lines() takes the first line before the last, not 6 to 3.');
});

it('reports a violation between two nodes at the lower one, and is brought in by a change to either', function () {
    $keys = nodesOf("<?php\n\nreturn [\n    'a' => 1,\n    'c' => 1,\n    'b' => 1,\n];\n", String_::class);

    $violation = Violation::between($keys[1], $keys[2], "Move 'b' above 'c'.");

    expect($violation->line)->toBe(6)
        ->and($violation->ranges)->toBe([[5, 5], [6, 6]])
        ->and($violation->message)->toBe("Move 'b' above 'c'.")
        ->and($violation->touches([5 => true]))->toBeTrue()
        ->and($violation->touches([6 => true]))->toBeTrue()
        ->and($violation->touches([4 => true, 7 => true]))->toBeFalse();
});

it('reports a violation between a node and the comment below it at the first line of the comment', function () {
    $statements = nodesOf("<?php\n\n\$names = [\n    'a',\n];\n// Count\n// them.\n\$count = count(\$names);\n", Expression::class);

    $violation = Violation::between($statements[0], $statements[1]->getComments()[0]);

    expect($violation->line)->toBe(6)
        ->and($violation->ranges)->toBe([[3, 5], [6, 6]]);
});

it('is brought in by a change to a comment above it', function () {
    $statements = nodesOf("<?php\n\n/* One\n   two */\n\$a = 1;\n\$b = 2;\n", Expression::class);

    $violation = Violation::between($statements[0]->getComments()[0], $statements[1]);

    expect($violation->line)->toBe(6)
        ->and($violation->ranges)->toBe([[3, 4], [6, 6]]);
});

it('is of the whole file when it has no lines, which no change touches', function () {
    $violation = Violation::file('Split it.');

    expect($violation->isWholeFile())->toBeTrue()
        ->and($violation->line)->toBeNull()
        ->and($violation->ranges)->toBe([])
        ->and($violation->message)->toBe('Split it.')
        ->and($violation->touches([1 => true]))->toBeFalse();
});

it('is of the whole file while shown at a line', function () {
    $violation = Violation::file('Fix the syntax.', line: 5);

    expect($violation->isWholeFile())->toBeTrue()
        ->and($violation->line)->toBe(5)
        ->and($violation->touches([5 => true]))->toBeFalse();
});

it('is touched by no change when none of its lines changed', function () {
    $violation = Violation::line(4);

    expect($violation->touches([]))->toBeFalse();
});

test('a violation is final, because the runner compares and prints it as it is', function () {
    expect((new ReflectionClass(Violation::class))->isFinal())->toBeTrue();
});
