<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\NoFunctionsInTests;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails every named function in a test file, and passes closures and methods', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Feature/OrderTest.php', <<<'PHP'
        <?php

        function order(): object
        {
            return new stdClass;
        }

        it('places an order', function () {
            function helper(): void
            {
                //
            }

            $place = fn () => order();
            $check = function () {};
        });

        class Builder
        {
            public function build(): void
            {
                //
            }
        }
        PHP);

    expect($file->lines(new NoFunctionsInTests))->toBe([3, 9]);
});

it('reads only the test files of the tests', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\nfunction helper(): void\n{\n}\n");

    expect($file->lines(new NoFunctionsInTests))->toBe($lines);
})->with([
    'a feature test' => ['tests/Feature/OrderTest.php', Kind::TESTS, [3]],
    'a nested unit test' => ['tests/Unit/Models/OrderTest.php', Kind::TESTS, [3]],
    'the Pest helper file' => ['tests/Pest.php', Kind::TESTS, []],
    'a test case' => ['tests/TestCase.php', Kind::TESTS, []],
    'a test file of the source code' => ['app/OrderTest.php', Kind::SOURCE, []],
]);

it('passes a function whose body the change edits', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Feature/OrderTest.php', "<?php\n\nfunction order(): int\n{\n    return 2;\n}\n")
        ->changedFrom("<?php\n\nfunction order(): int\n{\n    return 1;\n}\n");

    expect($file->lines(new NoFunctionsInTests))->toBe([]);
});
