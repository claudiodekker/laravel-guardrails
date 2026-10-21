<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\OneClausePerLine;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a condition of three clauses on one line', function (string $condition) {
    $file = TestFile::of(Kind::SOURCE, 'app/Policies/OrderPolicy.php', "<?php\n\nif ({$condition}) {\n    allow();\n}\n");

    expect($file->lines(new OneClausePerLine))->toBe([3]);
})->with([
    'and' => '$a && $b && $c',
    'or' => '$a || $b || $c',
    'the word and' => '$a and $b and $c',
    'and then or' => '$a && $b || $c',
]);

it('fails a group of three clauses on one line inside a condition', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Policies/OrderPolicy.php', "<?php\n\nif (\$a && (\$b || \$c || \$d)) {\n    allow();\n}\n");

    expect($file->lines(new OneClausePerLine))->toBe([3]);
});

it('fails a first clause that shares a line with the opening parenthesis', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Policies/OrderPolicy.php', <<<'PHP'
        <?php

        if ($a
            && $b
            && $c) {
            allow();
        }
        PHP);

    expect($file->lines(new OneClausePerLine))->toBe([3]);
});

it('fails an operator at the end of a line', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Policies/OrderPolicy.php', <<<'PHP'
        <?php

        if (
            $a &&
            $b &&
            $c
        ) {
            allow();
        }
        PHP);

    expect($file->lines(new OneClausePerLine))->toBe([5, 6]);
});

it('passes each clause on its own line with the operator first', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Policies/OrderPolicy.php', <<<'PHP'
        <?php

        if (
            $a
            && ($b || $c)
            && $d
        ) {
            allow();
        }

        return $a
            && $b
            && $c;
        PHP);

    expect($file->lines(new OneClausePerLine))->toBe([]);
});

it('passes a condition of two clauses on one line, however long each clause is', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Policies/OrderPolicy.php', "<?php\n\nif (\$a && \$b) {\n    allow();\n}\n\nif ((\$a || \$b) && ! \$c) {\n    allow();\n}\n");

    expect($file->lines(new OneClausePerLine))->toBe([]);
});
