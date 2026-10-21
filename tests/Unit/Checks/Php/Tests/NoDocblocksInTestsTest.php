<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\NoDocblocksInTests;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a docblock above a test', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Feature/ExampleTest.php', <<<'PHP'
        <?php

        /**
         * Sign the user in.
         */
        it('signs the user in', function () {
            //
        });
        PHP);

    expect($file->lines(new NoDocblocksInTests))->toBe([3]);
});

it('fails a docblock inside a test', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/ExampleTest.php', <<<'PHP'
        <?php

        it('signs the user in', function () {
            /**
             * The user to sign in.
             */
            $user = user();
        });
        PHP);

    expect($file->lines(new NoDocblocksInTests))->toBe([4]);
});

it('passes an inline @var hint on a variable', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/ExampleTest.php', <<<'PHP'
        <?php

        it('signs the user in', function () {
            /** @var \App\Models\User $user */
            $user = user();
        });
        PHP);

    expect($file->lines(new NoDocblocksInTests))->toBe([]);
});

it('passes a docblock of type and analyser tags, on one or more lines', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/ExampleTest.php', <<<'PHP'
        <?php

        /**
         * @template TModel of \App\Models\Model
         *
         * @param  class-string<TModel>  $class
         * @return TModel
         *
         * @throws \RuntimeException
         */
        function make(string $class): object
        {
            return new $class;
        }

        it('signs the user in', function () {
            /** @phpstan-var list<int> $ids */
            $ids = [];

            /** @psalm-suppress MixedAssignment */
            $user = user();
        });
        PHP);

    expect($file->lines(new NoDocblocksInTests))->toBe([]);
});

it('fails a docblock that holds prose beside its tags', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/ExampleTest.php', <<<'PHP'
        <?php

        /**
         * Make a model of the given class.
         *
         * @param  class-string  $class
         */
        function make(string $class): object
        {
            return new $class;
        }

        /**
         * @param  int  $id
         * Find the user.
         */
        function find(int $id): object
        {
            return user($id);
        }
        PHP);

    expect($file->lines(new NoDocblocksInTests))->toBe([3, 13]);
});

it('fails a docblock of tags that are not type, analyser or PHPUnit tags', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/ExampleTest.php', <<<'PHP'
        <?php

        /** @author Jane */
        function signs_the_user_in(): void
        {
            //
        }
        PHP);

    expect($file->lines(new NoDocblocksInTests))->toBe([3]);
});

it('passes a test with plain comments and docblock text in a string', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/ExampleTest.php', <<<'PHP'
        <?php

        // Signs the user in.
        it('signs the user in', function () {
            /* The user */
            $user = "/** not a docblock */";
        });
        PHP);

    expect($file->lines(new NoDocblocksInTests))->toBe([]);
});

it('reads only the test files of the tests', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\n/**\n * Run a test.\n */\nclass Example {}\n");

    expect($file->lines(new NoDocblocksInTests))->toBe($lines);
})->with([
    'a test' => ['tests/ExampleTest.php', Kind::TESTS, [3]],
    'a nested test' => ['tests/Unit/Models/ExampleTest.php', Kind::TESTS, [3]],
    'a test case' => ['tests/TestCase.php', Kind::TESTS, []],
    'a test file of the source code' => ['app/ExampleTest.php', Kind::SOURCE, []],
]);

it('fails a docblock the change edits below its first line, at the first line', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Feature/ExampleTest.php', "<?php\n\n/**\n * Sign the user out.\n */\nit('signs the user in', function () {\n    //\n});\n")
        ->changedFrom("<?php\n\n/**\n * Sign the user in.\n */\nit('signs the user in', function () {\n    //\n});\n");

    expect($file->lines(new NoDocblocksInTests))->toBe([3]);
});

it('passes a docblock that holds only PHPUnit annotations', function (string $docblock) {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/ExampleTest.php', "<?php\n\nclass ExampleTest\n{\n    {$docblock}\n    public function testIt(): void {}\n}\n");

    expect($file->lines(new NoDocblocksInTests))->toBe([]);
})->with([
    'test' => '/** @test */',
    'data provider' => "/**\n     * @dataProvider values\n     */",
    'several' => "/**\n     * @test\n     * @depends testOther\n     * @covers \\App\\Order\n     * @group slow\n     * @testWith [1, 2]\n     * @doesNotPerformAssertions\n     */",
    'mixed with a type tag' => "/**\n     * @test\n     * @param  int  \$value\n     */",
]);

it('fails a docblock that holds prose beside a PHPUnit annotation', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/ExampleTest.php', "<?php\n\nclass ExampleTest\n{\n    /**\n     * Sign the user in.\n     *\n     * @test\n     */\n    public function testIt(): void {}\n}\n");

    expect($file->lines(new NoDocblocksInTests))->toBe([5]);
});
