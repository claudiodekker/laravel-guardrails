<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\OneClassPerFile;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails every declaration after the first', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Example.php', <<<'PHP'
        <?php

        namespace App;

        class First
        {
            //
        }

        class Second
        {
            //
        }

        interface Third
        {
            //
        }

        trait Fourth
        {
            //
        }

        enum Fifth
        {
            //
        }
        PHP);

    expect($file->lines(new OneClassPerFile))->toBe([10, 15, 20, 25]);
});

it('passes a file with one class and an anonymous class inside it', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Example.php', <<<'PHP'
        <?php

        namespace App;

        class Example
        {
            public function run(): object
            {
                return new class {};
            }
        }
        PHP);

    expect($file->lines(new OneClassPerFile))->toBe([]);
});

it('passes a migration that returns an anonymous class', function () {
    $file = TestFile::of(Kind::MIGRATIONS, 'database/migrations/0001_01_01_000000_create_users_table.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;

        return new class extends Migration
        {
            public function up(): void
            {
                //
            }
        };
        PHP);

    expect($file->lines(new OneClassPerFile))->toBe([]);
});

it('passes a Pest test file with closures and no class', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/ExampleTest.php', <<<'PHP'
        <?php

        it('runs', function () {
            expect(true)->toBeTrue();
        });
        PHP);

    expect($file->lines(new OneClassPerFile))->toBe([]);
});

it('reads every php file', function () {
    $file = TestFile::of(Kind::SOURCE, 'database/seeders/Seeders.php', "<?php\n\nclass A {}\n\nclass B {}\n");

    expect($file->lines(new OneClassPerFile))->toBe([5]);
});

it('passes a second class whose body the change edits', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Example.php', "<?php\n\nclass First {}\n\nclass Second\n{\n    public int \$b = 2;\n}\n")
        ->changedFrom("<?php\n\nclass First {}\n\nclass Second\n{\n    public int \$b = 1;\n}\n");

    expect($file->lines(new OneClassPerFile))->toBe([]);
});

it('passes helper classes that sit in a test file', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Feature/InvoiceTest.php', "<?php\n\nclass FakeGateway {}\n\nclass FakeMailer {}\n");

    expect($file->lines(new OneClassPerFile))->toBe([])
        ->and(TestFile::of(Kind::SOURCE, 'app/Models/TensileTest.php', "<?php\n\nclass TensileTest {}\n\nclass Other {}\n")->lines(new OneClassPerFile))->toBe([5])
        ->and(TestFile::of(Kind::TESTS, 'tests/Support/Fakes.php', "<?php\n\nclass FakeGateway {}\n\nclass FakeMailer {}\n")->lines(new OneClassPerFile))->toBe([5]);
});

it('explains that a test file may hold helper classes', function () {
    expect((new OneClassPerFile)->explanation())->toContain('the helper classes a *Test.php file in the tests holds for its tests');
});

it('says it reads every PHP file except the test cases', function () {
    expect((new OneClassPerFile)->scope())->toBe('PHP except *Test.php files in the tests');
});
