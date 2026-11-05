<?php

use ClaudioDekker\LaravelGuardrails\Checks\Text\PhpstanLevelNeverLowers;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

$message = 'Keep the PHPStan level where it was, and fix the errors that level reports instead of lowering it.';

it('fails a change that lowers the level, at the level line, and passes one that raises it or keeps it', function (string $before, string $after, bool $fails) use ($message) {
    $neon = fn (string $level) => "includes:\n    - phpstan-baseline.neon\n\nparameters:\n    paths:\n        - src\n    level: {$level}\n";

    $file = TestFile::of(Kind::TEXT, 'phpstan.neon', $neon($after))->changedFrom($neon($before));

    expect($file->reports(new PhpstanLevelNeverLowers))->toBe($fails ? [['line' => 7, 'message' => $message]] : []);
})->with([
    'eight to five' => ['8', '5', true],
    'max to nine' => ['max', '9', true],
    'nine to zero' => ['9', '0', true],
    'five to six' => ['5', '6', false],
    'nine to max' => ['9', 'max', false],
    'max to max' => ['max', 'max', false],
    'eight to eight' => ['8', '8', false],
    'eight to eight, written differently' => ['8', "'8'", false],
    'max to max, written differently' => ['max', '"max" # as before', false],
    'ten to nine, which is where a digit count would fail' => ['10', '9', true],
    'nine to ten' => ['9', '10', false],
]);

it('reads a level that is quoted or followed by a comment, wherever it is indented', function (string $line) use ($message) {
    $before = "parameters:\n    level: 8\n";
    $file = TestFile::of(Kind::TEXT, 'phpstan.neon', "parameters:\n{$line}\n")->changedFrom($before);

    expect($file->reports(new PhpstanLevelNeverLowers))->toBe([['line' => 2, 'message' => $message]]);
})->with([
    'quoted' => ["    level: '5'"],
    'double quoted' => ['    level: "5"'],
    'commented' => ['    level: 5 # for now'],
    'tab indented' => ["\tlevel: 5"],
    'indented by two' => ['  level: 5'],
]);

it('fails a change that removes the level, and passes one that adds a level or never had one', function () use ($message) {
    $with = "parameters:\n    level: 8\n    paths:\n        - src\n";
    $without = "parameters:\n    paths:\n        - src\n";

    $removed = TestFile::of(Kind::TEXT, 'phpstan.neon', $without)->changedFrom($with);
    $added = TestFile::of(Kind::TEXT, 'phpstan.neon', $with)->changedFrom($without);
    $never = TestFile::of(Kind::TEXT, 'phpstan.neon', "{$without}        - tests\n")->changedFrom($without);

    expect($removed->reports(new PhpstanLevelNeverLowers))->toBe([['line' => null, 'message' => $message]])
        ->and($added->reports(new PhpstanLevelNeverLowers))->toBe([])
        ->and($never->reports(new PhpstanLevelNeverLowers))->toBe([]);
});

it('passes an edit that leaves the level alone, and a new configuration', function () {
    $edited = TestFile::of(Kind::TEXT, 'phpstan.neon', "parameters:\n    level: 8\n    paths:\n        - src\n        - tests\n")
        ->changedFrom("parameters:\n    level: 8\n    paths:\n        - src\n");

    $new = TestFile::of(Kind::TEXT, 'phpstan.neon', "parameters:\n    level: 1\n");

    expect($edited->reports(new PhpstanLevelNeverLowers))->toBe([])
        ->and($new->reports(new PhpstanLevelNeverLowers))->toBe([]);
});

it('fails a deleted configuration that set a level, and passes one that set none', function () use ($message) {
    $set = TestFile::of(Kind::TEXT, 'phpstan.neon', "parameters:\n    level: 8\n")->deleted();
    $unset = TestFile::of(Kind::TEXT, 'phpstan.neon', "parameters:\n    paths:\n        - src\n")->deleted();

    expect($set->reports(new PhpstanLevelNeverLowers))->toBe([['line' => null, 'message' => $message]])
        ->and($unset->reports(new PhpstanLevelNeverLowers))->toBe([]);
});

it('compares a configuration with the one it was renamed from', function () use ($message) {
    $renamed = TestFile::of(Kind::TEXT, 'phpstan.neon.dist', "parameters:\n    level: 8\n")->movedFrom('phpstan.neon');
    $lowered = TestFile::of(Kind::TEXT, 'phpstan.neon.dist', "parameters:\n    level: 5\n")->movedFrom('phpstan.neon')->changedFrom("parameters:\n    level: 8\n");
    $renamedAway = TestFile::of(Kind::TEXT, 'analysis.neon', "parameters:\n    level: 8\n")->movedFrom('phpstan.neon');

    expect($renamed->reports(new PhpstanLevelNeverLowers))->toBe([])
        ->and($lowered->reports(new PhpstanLevelNeverLowers))->toBe([['line' => 2, 'message' => $message]])
        ->and($renamedAway->reports(new PhpstanLevelNeverLowers))->toBe([['line' => null, 'message' => $message]]);
});

it('reads the configuration files of PHPStan and no other file', function (string $path, bool $reads) {
    $file = TestFile::of(Kind::TEXT, $path, "parameters:\n    level: 5\n")->changedFrom("parameters:\n    level: 8\n");

    expect($file->lines(new PhpstanLevelNeverLowers))->toBe($reads ? [2] : []);
})->with([
    'phpstan.neon' => ['phpstan.neon', true],
    'phpstan.neon.dist' => ['phpstan.neon.dist', true],
    'phpstan.dist.neon' => ['phpstan.dist.neon', true],
    'a configuration in a folder' => ['build/phpstan.neon', true],
    'the baseline' => ['phpstan-baseline.neon', false],
    'another NEON file' => ['config.neon', false],
]);
