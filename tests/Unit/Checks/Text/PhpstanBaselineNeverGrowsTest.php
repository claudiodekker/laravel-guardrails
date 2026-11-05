<?php

use ClaudioDekker\LaravelGuardrails\Checks\Text\PhpstanBaselineNeverGrows;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

$grows = [['line' => null, 'message' => 'Fix the errors PHPStan reports instead of adding them to the baseline, which may only shrink.']];

it('fails a change that makes the NEON baseline ignore more errors, and passes one that does not', function (array $before, array $after, bool $fails) use ($grows) {
    $baseline = fn (array $counts) => "parameters:\n    ignoreErrors:\n".implode('', array_map(
        fn (int $count, int $index) => "        -\n            message: '#^Error {$index}\\.\$#'\n            identifier: return.type\n            count: {$count}\n            path: src/Foo.php\n\n",
        $counts,
        array_keys($counts),
    ));

    $file = TestFile::of(Kind::TEXT, 'phpstan-baseline.neon', $baseline($after))->changedFrom($baseline($before));

    expect($file->reports(new PhpstanBaselineNeverGrows))->toBe($fails ? $grows : []);
})->with([
    'a new entry' => [[1, 1], [1, 1, 1], true],
    'a higher count' => [[1, 1], [1, 3], true],
    'an entry swapped for a count of two' => [[1, 1, 1], [1, 3], true],
    'the same size' => [[1, 2], [1, 2], false],
    'a fixed entry' => [[1, 1, 1], [1, 1], false],
    'a lower count' => [[1, 4], [1, 2], false],
    'one error fixed and one baselined' => [[1, 2], [2, 1], false],
    'an emptied baseline' => [[1, 4], [], false],
]);

it('counts an entry without a count key as one error, whatever its shape', function () use ($grows) {
    $before = <<<'NEON'
        parameters:
            ignoreErrors:
                - '#^Error one\.$#'
                -
                    rawMessage: Error two, count: 9
                    path: src/Foo.php
                - { message: '#^Error three\.$#', count: 2, path: src/Foo.php }
                - message: '#^Error four\.$#'
                  count: 3
        NEON;

    $unchanged = TestFile::of(Kind::TEXT, 'phpstan-baseline.neon', $before."\n")->changedFrom($before);
    $oneMore = TestFile::of(Kind::TEXT, 'phpstan-baseline.neon', "{$before}\n        - '#^Error five\\.\$#'\n")->changedFrom($before);
    $oneFewer = TestFile::of(Kind::TEXT, 'phpstan-baseline.neon', str_replace("        - '#^Error one\\.\$#'\n", '', $before))->changedFrom($before);
    $twoForOne = TestFile::of(Kind::TEXT, 'phpstan-baseline.neon', str_replace("        -\n            rawMessage: Error two, count: 9\n            path: src/Foo.php\n", "        - '#^Error five\\.\$#'\n        - '#^Error six\\.\$#'\n", $before))->changedFrom($before);

    expect($unchanged->reports(new PhpstanBaselineNeverGrows))->toBe([])
        ->and($oneMore->reports(new PhpstanBaselineNeverGrows))->toBe($grows)
        ->and($oneFewer->reports(new PhpstanBaselineNeverGrows))->toBe([])
        ->and($twoForOne->reports(new PhpstanBaselineNeverGrows))->toBe($grows);
});

it('adds up the entries under ignoreErrors and nothing else in the file', function () use ($grows) {
    $before = "includes:\n    - phpstan-extra.neon\n\nparameters:\n    paths:\n        - src\n        - tests\n    ignoreErrors:\n        - '#^Error one\\.\$#'\n    level: 5\n";
    $pathAdded = str_replace("        - tests\n", "        - tests\n        - app\n", $before);
    $errorAdded = str_replace("    level: 5\n", "        - '#^Error two\\.\$#'\n    level: 5\n", $before);

    expect(TestFile::of(Kind::TEXT, 'phpstan-baseline.neon', $pathAdded)->changedFrom($before)->reports(new PhpstanBaselineNeverGrows))->toBe([])
        ->and(TestFile::of(Kind::TEXT, 'phpstan-baseline.neon', $errorAdded)->changedFrom($before)->reports(new PhpstanBaselineNeverGrows))->toBe($grows);
});

it('fails a change that makes the PHP baseline ignore more errors, and passes one that does not', function () use ($grows) {
    $entry = fn (string $message, ?int $count) => "\$ignoreErrors[] = [\n    'message' => '#^{$message}\\\\.\$#',\n    'identifier' => 'return.type',\n".($count === null ? '' : "    'count' => {$count},\n")."    'path' => __DIR__ . '/src/Foo.php',\n];\n";
    $baseline = fn (string ...$entries) => "<?php declare(strict_types = 1);\n\n\$ignoreErrors = [];\n".implode('', $entries)."\nreturn ['parameters' => ['ignoreErrors' => \$ignoreErrors]];\n";

    $before = $baseline($entry('Error one', 1), $entry('Error two', 2));
    $grown = $baseline($entry('Error one', 1), $entry('Error two', 3));
    $extra = $baseline($entry('Error one', 1), $entry('Error two', 2), $entry('Error three', null));
    $shrunk = $baseline($entry('Error one', 1), $entry('Error two', 1));

    expect(TestFile::of(Kind::TEXT, 'phpstan-baseline.php', $grown)->changedFrom($before)->reports(new PhpstanBaselineNeverGrows))->toBe($grows)
        ->and(TestFile::of(Kind::TEXT, 'phpstan-baseline.php', $extra)->changedFrom($before)->reports(new PhpstanBaselineNeverGrows))->toBe($grows)
        ->and(TestFile::of(Kind::TEXT, 'phpstan-baseline.php', $shrunk)->changedFrom($before)->reports(new PhpstanBaselineNeverGrows))->toBe([])
        ->and(TestFile::of(Kind::TEXT, 'phpstan-baseline.php', $before)->changedFrom($before)->reports(new PhpstanBaselineNeverGrows))->toBe([]);
});

it('fails a new baseline that ignores errors, and passes one that ignores none', function (string $path, string $contents, bool $fails) use ($grows) {
    expect(TestFile::of(Kind::TEXT, $path, $contents)->reports(new PhpstanBaselineNeverGrows))->toBe($fails ? $grows : []);
})->with([
    'a NEON baseline with an error' => ['phpstan-baseline.neon', "parameters:\n    ignoreErrors:\n        - '#^Error one\\.\$#'\n", true],
    'a PHP baseline with an error' => ['phpstan-baseline.php', "<?php\n\n\$ignoreErrors = [];\n\$ignoreErrors[] = [\n    'message' => '#^Error one\\.\$#',\n    'count' => 1,\n    'path' => __DIR__ . '/src/Foo.php',\n];\n\nreturn ['parameters' => ['ignoreErrors' => \$ignoreErrors]];\n", true],
    'an empty NEON baseline' => ['phpstan-baseline.neon', "parameters:\n    ignoreErrors: []\n", false],
    'an empty PHP baseline' => ['phpstan-baseline.php', "<?php declare(strict_types = 1);\n\nreturn [];\n", false],
]);

it('counts a baseline moved to another path against the one it came from', function () use ($grows) {
    $before = "parameters:\n    ignoreErrors:\n        - '#^Error one\\.\$#'\n";
    $moved = TestFile::of(Kind::TEXT, 'build/phpstan-baseline.neon', $before)->movedFrom('phpstan-baseline.neon');
    $moveAndGrow = TestFile::of(Kind::TEXT, 'build/phpstan-baseline.neon', "{$before}        - '#^Error two\\.\$#'\n")->movedFrom('phpstan-baseline.neon', Kind::TEXT)->changedFrom($before);

    expect($moved->reports(new PhpstanBaselineNeverGrows))->toBe([])
        ->and($moveAndGrow->reports(new PhpstanBaselineNeverGrows))->toBe($grows);
});

it('passes a deleted baseline', function () {
    $file = TestFile::of(Kind::TEXT, 'phpstan-baseline.neon', "parameters:\n    ignoreErrors:\n        - '#^Error one\\.\$#'\n")->deleted();

    expect($file->reports(new PhpstanBaselineNeverGrows))->toBe([]);
});

it('reads the baselines and the split baselines named after them, and no other file', function (string $path, bool $reads) use ($grows) {
    $file = TestFile::of(Kind::TEXT, $path, "parameters:\n    ignoreErrors:\n        - '#^Error one\\.\$#'\n");

    expect($file->reports(new PhpstanBaselineNeverGrows))->toBe($reads ? $grows : []);
})->with([
    'the baseline' => ['phpstan-baseline.neon', true],
    'a baseline in a folder' => ['build/phpstan-baseline.neon', true],
    'a split baseline' => ['phpstan-baseline-level-5.neon', true],
    'the configuration' => ['phpstan.neon', false],
    'another NEON file' => ['baseline.neon', false],
]);
