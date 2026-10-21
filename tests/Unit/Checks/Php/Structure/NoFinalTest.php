<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\NoFinal;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a final class', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        final class Invoices
        {
            //
        }

        final readonly class Totals
        {
            //
        }
        PHP);

    expect($file->lines(new NoFinal))->toBe([3, 8]);
});

it('fails a final method, constant and property', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        class Invoices
        {
            final public const PREFIX = 'INV';

            final public string $prefix;

            final public function send(): void
            {
                //
            }
        }
        PHP);

    expect($file->lines(new NoFinal))->toBe([5, 7, 9]);
});

it('passes a class that can be extended, and the word final in strings', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        abstract class Invoices
        {
            public const STATUS = 'final';

            protected function status(): string
            {
                return 'final';
            }
        }
        PHP);

    expect($file->lines(new NoFinal))->toBe([]);
});

it('reads every PHP file', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, "<?php\n\nfinal class Example {}\n");

    expect($file->lines(new NoFinal))->toBe([3]);
})->with([
    'application code' => 'app/Services/Example.php',
    'a migration' => 'database/migrations/2024_01_01_000000_create_examples_table.php',
    'test support code' => 'tests/Support/Example.php',
    'a test' => 'tests/Unit/ExampleTest.php',
]);

it('passes a final method whose body the change edits', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', "<?php\n\nclass Invoices\n{\n    final public function send(): int\n    {\n        return 2;\n    }\n}\n")
        ->changedFrom("<?php\n\nclass Invoices\n{\n    final public function send(): int\n    {\n        return 1;\n    }\n}\n");

    expect($file->lines(new NoFinal))->toBe([]);
});

it('reports a final constant and property with an attribute above them at the line of the declaration', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', "<?php\n\nclass Invoices\n{\n    #[Deprecated]\n    final public const PREFIX = 'INV';\n\n    #[Column]\n    final public string \$prefix;\n}\n");

    expect($file->lines(new NoFinal))->toBe([6, 9]);
});
