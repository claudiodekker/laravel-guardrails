<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\TestsInUnitOrFeature;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('passes a test in a Unit or Feature folder, at any depth', function (string $path) {
    $file = TestFile::of(Kind::TESTS, $path, "<?php\n\nit('does a thing', function () {});\n");

    expect($file->lines(new TestsInUnitOrFeature))->toBe([]);
})->with([
    'tests/Unit/ExampleTest.php',
    'tests/Feature/Http/ExampleTest.php',
    'packages/billing/tests/Unit/Actions/ExampleTest.php',
]);

it('fails a test outside both folders', function (string $path) {
    $file = TestFile::of(Kind::TESTS, $path, "<?php\n\nit('does a thing', function () {});\n");

    expect(array_column($file->reports(new TestsInUnitOrFeature), 'line'))->toBe([null]);
})->with([
    'tests/ExampleTest.php',
    'tests/Browser/ExampleTest.php',
    'tests/Features/ExampleTest.php',
    'packages/billing/tests/Smoke/ExampleTest.php',
]);

it('leaves the support code, the fixtures and files of other kinds alone', function (string $path, Kind $kind) {
    $file = TestFile::of($kind, $path, "<?php\n\nit('does a thing', function () {});\n");

    expect($file->lines(new TestsInUnitOrFeature))->toBe([]);
})->with([
    'a test case' => ['tests/TestCase.php', Kind::TESTS],
    'support code' => ['tests/Support/Example.php', Kind::TESTS],
    'a fixture' => ['tests/Fixtures/skeleton/tests/ExampleTest.php', Kind::TESTS],
    'a test in the source code' => ['app/Testing/ExampleTest.php', Kind::SOURCE],
]);
