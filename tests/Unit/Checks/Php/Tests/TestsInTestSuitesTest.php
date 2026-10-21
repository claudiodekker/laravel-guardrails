<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\TestsInTestSuites;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

$suites = <<<'XML'
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit>
        <testsuites>
            <testsuite name="Unit">
                <directory>tests/Unit</directory>
            </testsuite>
            <testsuite name="Feature">
                <directory>tests/Feature</directory>
                <exclude>tests/Feature/Legacy</exclude>
                <exclude>tests/Feature/SkippedTest.php</exclude>
            </testsuite>
            <testsuite name="Integration">
                <directory>./tests/Integration/</directory>
                <file>tests/SmokeTest.php</file>
            </testsuite>
        </testsuites>
    </phpunit>
    XML;

it('passes a test in a folder a test suite of phpunit.xml runs', function (string $path) use ($suites) {
    $file = TestFile::of(Kind::TESTS, $path, "<?php\n\nit('does a thing', function () {});\n")->in(projectWith(['phpunit.xml' => $suites]));

    expect($file->lines(new TestsInTestSuites))->toBe([]);
})->with([
    'tests/Unit/ExampleTest.php',
    'tests/Unit/Actions/ExampleTest.php',
    'tests/Feature/Http/ExampleTest.php',
    'tests/Feature/LegacyTest.php',
    'tests/Integration/ApiTest.php',
    'tests/SmokeTest.php',
]);

it('fails a test outside every test suite of phpunit.xml and names the suite folders', function (string $path) use ($suites) {
    $file = TestFile::of(Kind::TESTS, $path, "<?php\n\nit('does a thing', function () {});\n")->in(projectWith(['phpunit.xml' => $suites]));
    $check = new TestsInTestSuites;

    expect($file->reports($check))->toEqual([['line' => null, 'message' => 'Move the test into tests/Unit, tests/Feature or tests/Integration, or add its folder to a test suite in phpunit.xml.']]);
})->with([
    'tests/ExampleTest.php',
    'tests/Browser/ExampleTest.php',
    'tests/Http/Feature/ExampleTest.php',
    'tests/Feature/Legacy/ExampleTest.php',
    'tests/Feature/SkippedTest.php',
]);

it('reads phpunit.xml.dist when there is no phpunit.xml, and phpunit.xml over it', function () use ($suites) {
    $dist = projectWith(['phpunit.xml.dist' => $suites]);
    $both = projectWith(['phpunit.xml.dist' => $suites, 'phpunit.xml' => str_replace('tests/Integration', 'tests/EndToEnd', $suites)]);
    $check = new TestsInTestSuites;

    expect(TestFile::of(Kind::TESTS, 'tests/Integration/ApiTest.php', '<?php')->in($dist)->lines($check))->toBe([])
        ->and(TestFile::of(Kind::TESTS, 'tests/Integration/ApiTest.php', '<?php')->in($both)->reports($check))->toEqual([['line' => null, 'message' => 'Move the test into tests/Unit, tests/Feature or tests/EndToEnd, or add its folder to a test suite in phpunit.xml.']])
        ->and(TestFile::of(Kind::TESTS, 'tests/EndToEnd/ApiTest.php', '<?php')->in($both)->lines($check))->toBe([])
        ->and(TestFile::of(Kind::TESTS, 'tests/ApiTest.php', '<?php')->in($dist)->reports($check))->toEqual([['line' => null, 'message' => 'Move the test into tests/Unit, tests/Feature or tests/Integration, or add its folder to a test suite in phpunit.xml.dist.']]);
});

it('falls back to tests/Unit, tests/Feature and tests/Browser without a phpunit.xml', function () {
    $root = projectWith([]);
    $check = new TestsInTestSuites;
    $outside = TestFile::of(Kind::TESTS, 'tests/Http/ExampleTest.php', '<?php')->in($root);

    expect(TestFile::of(Kind::TESTS, 'tests/Unit/ExampleTest.php', '<?php')->in($root)->lines($check))->toBe([])
        ->and(TestFile::of(Kind::TESTS, 'tests/Feature/Http/ExampleTest.php', '<?php')->in($root)->lines($check))->toBe([])
        ->and(TestFile::of(Kind::TESTS, 'tests/Browser/ExampleTest.php', '<?php')->in($root)->lines($check))->toBe([])
        ->and($outside->reports($check))->toEqual([['line' => null, 'message' => 'Move the test into tests/Unit, tests/Feature or tests/Browser, or add a phpunit.xml whose test suites run its folder.']]);
});

it('falls back to tests/Unit, tests/Feature and tests/Browser for a file from no project', function () {
    $check = new TestsInTestSuites;
    $outside = TestFile::of(Kind::TESTS, 'tests/Http/ExampleTest.php', '<?php');

    expect(TestFile::of(Kind::TESTS, 'tests/Unit/ExampleTest.php', '<?php')->lines($check))->toBe([])
        ->and($outside->reports($check))->toEqual([['line' => null, 'message' => 'Move the test into tests/Unit, tests/Feature or tests/Browser, or add a phpunit.xml whose test suites run its folder.']]);
});

it('leaves the support code and the fixtures alone', function (string $path) {
    $file = TestFile::of(Kind::TESTS, $path, "<?php\n\nit('does a thing', function () {});\n")->in(projectWith([]));

    expect($file->lines(new TestsInTestSuites))->toBe([]);
})->with([
    'tests/TestCase.php',
    'tests/Support/Example.php',
    'tests/Fixtures/skeleton/tests/ExampleTest.php',
    'packages/billing/tests/Fixtures/ExampleTest.php',
]);

it('leaves a test file that is not of the tests kind alone', function () {
    $file = TestFile::of(Kind::SOURCE, 'tests/ExampleTest.php', "<?php\n\nit('does a thing', function () {});\n")->in(projectWith([]));

    expect($file->lines(new TestsInTestSuites))->toBe([]);
});

it('reads the tests of a package in a monorepo against the test suites with a wildcard', function () {
    $root = projectWith(['phpunit.xml' => '<phpunit><testsuites><testsuite name="Packages"><directory>packages/*/tests</directory></testsuite></testsuites></phpunit>']);
    $check = new TestsInTestSuites;
    $outside = TestFile::of(Kind::TESTS, 'packages/billing/testing/ExampleTest.php', '<?php')->in($root);

    expect(TestFile::of(Kind::TESTS, 'packages/billing/tests/Unit/ExampleTest.php', '<?php')->in($root)->lines($check))->toBe([])
        ->and($outside->reports($check))->toEqual([['line' => null, 'message' => 'Move the test into packages/*/tests, or add its folder to a test suite in phpunit.xml.']]);
});

it('fails a test file outside every root of the project, which no test suite runs', function () {
    $outside = TestFile::of(Kind::TEXT, 'tests/OrphanTest.php', '<?php')->in(projectWith(['phpunit.xml' => '<phpunit><testsuites><testsuite name="Packages"><directory>packages/*/tests</directory></testsuite></testsuites></phpunit>']));

    expect($outside->reports(new TestsInTestSuites))->toEqual([['line' => null, 'message' => 'Move the test into packages/*/tests, or add its folder to a test suite in phpunit.xml.']])
        ->and((new TestsInTestSuites)->scope())->toBe('*Test.php in tests and other files, outside Fixtures folders');
});
