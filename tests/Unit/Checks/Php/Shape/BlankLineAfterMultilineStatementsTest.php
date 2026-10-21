<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\BlankLineAfterMultilineStatements;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a statement that follows a multiline statement directly', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/helpers.php', <<<'PHP'
        <?php

        function totals()
        {
            $names = [
                'a',
                'b',
            ];
            $count = count($names);

            $total = sum(
                $count,
                1,
            );
            return $total;
        }
        PHP);

    expect($file->lines(new BlankLineAfterMultilineStatements))->toBe([9, 15]);
});

it('fails the comment that follows a multiline statement directly', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/helpers.php', <<<'PHP'
        <?php

        $names = [
            'a',
        ];
        // Count them.
        $count = count($names);
        PHP);

    expect($file->lines(new BlankLineAfterMultilineStatements))->toBe([6]);
});

it('fails a member of a class and a statement of a closure alike', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        class Invoices
        {
            public function first(): void
            {
                //
            }
            public function second(): callable
            {
                return function () {
                    $names = [
                        'a',
                    ];
                    return $names;
                };
            }
        }
        PHP);

    expect($file->lines(new BlankLineAfterMultilineStatements))->toBe([9, 15]);
});

it('passes a multiline statement followed by a blank line, and one-line statements together', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/helpers.php', <<<'PHP'
        <?php

        function totals()
        {
            $count = 1;
            $names = [
                'a',
            ];

            $total = sum(
                $count,
                1,
            );

            return [
                $names,
                $total,
            ];
        }
        PHP);

    expect($file->lines(new BlankLineAfterMultilineStatements))->toBe([]);
});

it('fails a statement the change crowds by inserting a multiline statement above it', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/helpers.php', "<?php\n\nfunction run(): void\n{\n    \$a = 1;\n    \$c = [\n        1,\n    ];\n    \$b = 2;\n}\n")
        ->changedFrom("<?php\n\nfunction run(): void\n{\n    \$a = 1;\n    \$b = 2;\n}\n");

    expect($file->lines(new BlankLineAfterMultilineStatements))->toBe([9]);
});

it('fails a crowded statement the change edits', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/helpers.php', "<?php\n\nfunction run(): void\n{\n    \$c = [\n        1,\n    ];\n    \$b = 3;\n}\n")
        ->changedFrom("<?php\n\nfunction run(): void\n{\n    \$c = [\n        1,\n    ];\n    \$b = 2;\n}\n");

    expect($file->lines(new BlankLineAfterMultilineStatements))->toBe([8]);
});

it('fails a comment the change types between a multiline statement and the next', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/helpers.php', "<?php\n\n\$names = [\n    'a',\n];\n// Count them.\n\$count = count(\$names);\n")
        ->changedFrom("<?php\n\n\$names = [\n    'a',\n];\n\n\$count = count(\$names);\n");

    expect($file->lines(new BlankLineAfterMultilineStatements))->toBe([6]);
});

it('fails a statement the change crowds by deleting the blank line above it', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Run.php', "<?php\n\nfunction run(): void\n{\n    \$c = [\n        1,\n    ];\n    \$b = 2;\n}\n")
        ->changedFrom("<?php\n\nfunction run(): void\n{\n    \$c = [\n        1,\n    ];\n\n    \$b = 2;\n}\n");

    expect($file->lines(new BlankLineAfterMultilineStatements))->toBe([8]);
});
