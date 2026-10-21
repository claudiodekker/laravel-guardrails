<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Comments\DocumentedDeclarations;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a property, method and function without a docblock', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        class Invoices
        {
            protected string $prefix;

            public function send(): void
            {
                //
            }
        }

        function invoices(): Invoices
        {
            return new Invoices;
        }
        PHP);

    expect($file->lines(new DocumentedDeclarations))->toBe([5, 7, 13]);
});

it('fails the methods of an interface, trait and enum without a docblock', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        interface Sends
        {
            public function send(): void;
        }

        trait SendsInvoices
        {
            public function send(): void
            {
                //
            }
        }

        enum Status
        {
            public function label(): string
            {
                return 'paid';
            }
        }
        PHP);

    expect($file->lines(new DocumentedDeclarations))->toBe([5, 10, 18]);
});

it('passes declarations that have a docblock, and classes without one', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        class Invoices
        {
            /**
             * The prefix of every invoice number.
             */
            protected string $prefix;

            /**
             * Send the invoice.
             */
            public function send(): void
            {
                //
            }
        }

        /**
         * Get the invoices.
         */
        function invoices(): Invoices
        {
            return new Invoices;
        }
        PHP);

    expect($file->lines(new DocumentedDeclarations))->toBe([]);
});

it('counts a constructor docblock as the docblock of its promoted properties', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        class Invoices
        {
            /**
             * Create the invoices.
             */
            public function __construct(protected string $prefix)
            {
                //
            }
        }
        PHP);

    expect($file->lines(new DocumentedDeclarations))->toBe([]);
});

it('does not ask a closure or an anonymous class for a docblock', function () {
    $file = TestFile::of(Kind::MIGRATIONS, 'database/migrations/2024_01_01_000000_create_invoices_table.php', <<<'PHP'
        <?php

        $double = fn (int $number) => $number * 2;

        return new class {
            /**
             * Run the migrations.
             */
            public function up(): void
            {
                //
            }
        };
        PHP);

    expect($file->lines(new DocumentedDeclarations))->toBe([]);
});

it('does not ask the members of an anonymous class for a docblock', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        /**
         * Hold the invoices.
         */
        class Invoices
        {
            /**
             * Get the gateway.
             */
            public function gateway(): object
            {
                return new class {
                    public int $attempts = 0;

                    public function charge(): void
                    {
                        //
                    }
                };
            }

            public function undocumented(): void
            {
                //
            }
        }
        PHP);

    expect($file->lines(new DocumentedDeclarations))->toBe([23]);
});

it('says in its explanation that the members of an anonymous class need no docblock', function () {
    expect((new DocumentedDeclarations)->explanation())->toContain('The members of an anonymous class need none.');
});

it('reads every kind of code and the test support code, but not a test case', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\nfunction helper() {}\n");

    expect($file->lines(new DocumentedDeclarations))->toBe($lines);
})->with([
    'source code' => ['app/Support/helpers.php', Kind::SOURCE, [3]],
    'a seeder' => ['database/seeders/DatabaseSeeder.php', Kind::TEXT, [3]],
    'a config file' => ['config/app.php', Kind::CONFIG, [3]],
    'a migration' => ['database/migrations/2026_01_01_000000_create_orders_table.php', Kind::MIGRATIONS, [3]],
    'test support code' => ['tests/Support/helpers.php', Kind::TESTS, [3]],
    'the Pest file' => ['tests/Pest.php', Kind::TESTS, [3]],
    'the Pest file of an app in a subfolder' => ['backend/tests/Pest.php', Kind::TESTS, [3]],
    'a test case' => ['tests/Feature/InvoiceTest.php', Kind::TESTS, []],
    'a class named like a test in the source code' => ['app/Models/TensileTest.php', Kind::SOURCE, [3]],
]);

it('says it reads every PHP file except the test cases', function () {
    expect((new DocumentedDeclarations)->scope())->toBe('PHP except *Test.php files in the tests');
});

it('names its classes option at the end of its explanation unless classes are on', function () {
    expect((new DocumentedDeclarations)->explanation())->toEndWith('Pass classes: true to flag classes too.')
        ->and((new DocumentedDeclarations(classes: true))->explanation())->not->toContain('Pass classes: true');
});

it('also fails a class, interface, trait and enum without a docblock when asked for classes', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        class Invoices {}

        interface Sends {}

        trait SendsInvoices {}

        enum Status {}

        /**
         * Hold the invoices.
         */
        class Documented {}

        $anonymous = new class {};
        PHP);

    expect($file->lines(new DocumentedDeclarations(classes: true)))->toBe([3, 5, 7, 9])
        ->and($file->lines(new DocumentedDeclarations))->toBe([]);
});

it('words the description and the message for classes when asked for them', function () {
    $default = new DocumentedDeclarations;
    $classes = new DocumentedDeclarations(classes: true);

    expect($classes->description())->toBe('Every class, method, function and property has a docblock.')
        ->and($default->description())->toBe('Every method, function and property has a docblock.')
        ->and($classes->message())->toContain('class')
        ->and($default->message())->not->toContain('class');
});

it('passes an undocumented method whose body the change edits', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', "<?php\n\nclass Invoices\n{\n    public function send(): int\n    {\n        return 2;\n    }\n}\n")
        ->changedFrom("<?php\n\nclass Invoices\n{\n    public function send(): int\n    {\n        return 1;\n    }\n}\n");

    expect($file->lines(new DocumentedDeclarations))->toBe([]);
});

it('fails an undocumented declaration whose attribute the change edits, at the line of the attribute', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', "<?php\n\nclass Invoices\n{\n    #[Column]\n    protected string \$prefix;\n\n    #[Deprecated]\n    public function send(): void\n    {\n        //\n    }\n}\n")
        ->changedFrom("<?php\n\nclass Invoices\n{\n    #[Col]\n    protected string \$prefix;\n\n    #[Pure]\n    public function send(): void\n    {\n        //\n    }\n}\n");

    $class = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', "<?php\n\n#[Attribute]\nclass Invoices\n{\n}\n")
        ->changedFrom("<?php\n\n#[Deprecated]\nclass Invoices\n{\n}\n");

    expect($file->lines(new DocumentedDeclarations))->toBe([5, 8])
        ->and($class->lines(new DocumentedDeclarations(classes: true)))->toBe([3]);
});

it('fails a method whose docblock the change deletes', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Foo.php', "<?php\n\nclass Foo\n{\n    public function name(): string\n    {\n        return 'a';\n    }\n}\n")
        ->changedFrom("<?php\n\nclass Foo\n{\n    /**\n     * Get the name.\n     */\n    public function name(): string\n    {\n        return 'a';\n    }\n}\n");

    expect(array_column($file->reports(new DocumentedDeclarations), 'line'))->toBe([5]);
});
