<?php

use ClaudioDekker\LaravelGuardrails\Checks\Text\NoPhpUnitExclusions;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails each group, directory and file an exclude lists, and the exclude of a test suite', function () {
    $file = TestFile::of(Kind::TEXT, 'phpunit.xml', <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <phpunit bootstrap="vendor/autoload.php">
            <testsuites>
                <testsuite name="Unit">
                    <directory>tests/Unit</directory>
                    <exclude>tests/Unit/Slow</exclude>
                </testsuite>
            </testsuites>
            <groups>
                <exclude>
                    <group>slow</group>
                    <group>flaky</group>
                </exclude>
            </groups>
            <source>
                <include>
                    <directory>src</directory>
                </include>
                <exclude>
                    <directory>src/Legacy</directory>
                    <file>src/helpers.php</file>
                </exclude>
            </source>
        </phpunit>
        XML);

    expect($file->reports(new NoPhpUnitExclusions))->toBe([
        ['line' => 6, 'message' => 'Remove the exclusion and fix the tests or the code it hides, because an excluded test or file is no longer checked.'],
        ['line' => 11, 'message' => 'Remove the exclusion and fix the tests or the code it hides, because an excluded test or file is no longer checked.'],
        ['line' => 12, 'message' => 'Remove the exclusion and fix the tests or the code it hides, because an excluded test or file is no longer checked.'],
        ['line' => 20, 'message' => 'Remove the exclusion and fix the tests or the code it hides, because an excluded test or file is no longer checked.'],
        ['line' => 21, 'message' => 'Remove the exclusion and fix the tests or the code it hides, because an excluded test or file is no longer checked.'],
    ]);
});

it('passes a configuration that only includes, and an exclude that lists nothing', function () {
    $file = TestFile::of(Kind::TEXT, 'phpunit.xml', <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <phpunit>
            <testsuites>
                <testsuite name="Unit">
                    <directory>tests/Unit</directory>
                    <file>tests/ExampleTest.php</file>
                </testsuite>
            </testsuites>
            <groups>
                <include>
                    <group>fast</group>
                </include>
                <exclude/>
            </groups>
            <source>
                <include>
                    <directory>src</directory>
                </include>
            </source>
        </phpunit>
        XML);

    expect($file->lines(new NoPhpUnitExclusions))->toBe([]);
});

it('passes a configuration that does not parse', function (string $contents) {
    $file = TestFile::of(Kind::TEXT, 'phpunit.xml', $contents);

    expect($file->lines(new NoPhpUnitExclusions))->toBe([]);
})->with([
    'an unclosed element' => ["<phpunit>\n    <groups>\n        <exclude><group>slow</group></exclude>\n"],
    'text' => ['not xml'],
    'nothing' => [''],
]);

it('fails only the exclusion the change adds', function () {
    $before = "<phpunit>\n    <groups>\n        <exclude>\n            <group>slow</group>\n        </exclude>\n    </groups>\n</phpunit>\n";
    $after = "<phpunit>\n    <groups>\n        <exclude>\n            <group>slow</group>\n            <group>flaky</group>\n        </exclude>\n    </groups>\n</phpunit>\n";

    $file = TestFile::of(Kind::TEXT, 'phpunit.xml', $after)->changedFrom($before);

    expect($file->lines(new NoPhpUnitExclusions))->toBe([5]);
});

it('reads the configuration files of PHPUnit and no other file', function (string $path, bool $reads) {
    $file = TestFile::of(Kind::TEXT, $path, "<phpunit>\n    <groups>\n        <exclude>\n            <group>slow</group>\n        </exclude>\n    </groups>\n</phpunit>\n");

    expect($file->lines(new NoPhpUnitExclusions))->toBe($reads ? [4] : []);
})->with([
    'phpunit.xml' => ['phpunit.xml', true],
    'phpunit.xml.dist' => ['phpunit.xml.dist', true],
    'phpunit.dist.xml' => ['phpunit.dist.xml', true],
    'a configuration in a folder' => ['build/phpunit.xml', true],
    'another XML file' => ['phpstan.xml', false],
    'a coverage report' => ['clover.xml', false],
]);
