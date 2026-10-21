<?php

use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a file over 20000 bytes, whatever its type', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, str_repeat('a', 20001));

    expect(array_column($file->reports(new FilesWithinTokenBudget), 'line'))->toBe([null]);
})->with([
    'app/Models/User.php',
    'README.md',
    'resources/js/app.ts',
    'my docs/Big File.md',
]);

it('passes a file exactly at 20000 bytes', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', str_repeat('a', 20000));

    expect($file->lines(new FilesWithinTokenBudget))->toBe([]);
});

it('counts bytes, not characters', function () {
    $file = TestFile::of(Kind::TEXT, 'README.md', str_repeat("\u{2013}", 7000));

    expect(array_column($file->reports(new FilesWithinTokenBudget), 'line'))->toBe([null]);
});

it('tells a single enum over the budget to move its data out', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Enums/Plan.php', "<?php\n\nenum Plan: string\n{\n    case Free = 'free';\n}\n");

    expect($file->reports(new FilesWithinTokenBudget(limit: 10)))->toEqual([['line' => null, 'message' => "Move the data of the enum's cases out of the enum, for example into a database table or a config file, until the file is under 10 tokens (40 bytes)."]]);
});

it('tells every other file over the budget to split', function (string $path, string $contents) {
    $file = TestFile::of(Kind::TEXT, $path, $contents);

    expect(array_column($file->reports(new FilesWithinTokenBudget(limit: 1)), 'line'))->toBe([null]);
})->with([
    'a class' => ['app/Models/User.php', "<?php\n\nclass User {}\n"],
    'an enum beside a class' => ['app/Enums/Plan.php', "<?php\n\nenum Plan {}\n\nclass Price {}\n"],
    'PHP that does not parse' => ['app/Enums/Plan.php', "<?php\n\nenum Plan {\n"],
    'a Markdown document' => ['README.md', 'enum Plan {}'],
]);

it('fails a file over a limit given through the constructor, and passes one at it', function () {
    $check = new FilesWithinTokenBudget(limit: 1000);

    expect(array_column(TestFile::of(Kind::TEXT, 'README.md', str_repeat('a', 4001))->reports($check), 'line'))->toBe([null])
        ->and(TestFile::of(Kind::TEXT, 'README.md', str_repeat('a', 4000))->lines($check))->toBe([]);
});

it('names the limit given through the constructor in its description, explanation and messages', function () {
    $check = new FilesWithinTokenBudget(limit: 1000);

    expect($check->description())->toBe('Every file stays under 1000 tokens, so an agent can read it whole.')
        ->and($check->explanation())->toContain('over 4000 bytes, counted as 1000 tokens')
        ->and($check->message())->toStartWith('Split the file into files under 1000 tokens (4000 bytes) each.')
        ->and($check->message())->toContain('3. Keep each new file under 1000 tokens')
        ->and(TestFile::of(Kind::SOURCE, 'app/Enums/Plan.php', "<?php\n\nenum Plan: string\n{\n    case Free = 'free';\n}\n")->reports(new FilesWithinTokenBudget(limit: 10)))->toEqual([['line' => null, 'message' => "Move the data of the enum's cases out of the enum, for example into a database table or a config file, until the file is under 10 tokens (40 bytes)."]]);
});

it('names its limit option at the end of its explanation, whatever limit it was given', function (FilesWithinTokenBudget $check) {
    expect($check->explanation())->toEndWith('Pass a limit to set another budget, as in new FilesWithinTokenBudget(limit: 8000).');
})->with([
    'the default limit' => [new FilesWithinTokenBudget],
    'another limit' => [new FilesWithinTokenBudget(limit: 1000)],
]);

it('fails an oversized file only when the change makes it hold more tokens', function (string $before, string $after, array $lines) {
    $file = TestFile::of(Kind::TEXT, 'README.md', $after)->changedFrom($before);

    expect(array_column($file->reports(new FilesWithinTokenBudget), 'line'))->toBe($lines);
})->with([
    'a file grown over the budget' => [str_repeat('a', 20000), str_repeat('a', 20001), [null]],
    'a file grown by a whole token' => [str_repeat('a', 20001), str_repeat('a', 20005), [null]],
    'a file grown within its last token' => [str_repeat('a', 20001), str_repeat('a', 20002), []],
    'a file edited without growing' => [str_repeat('a', 20001), str_repeat('b', 20001), []],
    'a file shrunk but still over the budget' => [str_repeat('a', 30000), str_repeat('a', 20001), []],
]);

it('refuses a limit under one token', function (int $limit) {
    expect(fn () => new FilesWithinTokenBudget(limit: $limit))
        ->toThrow(InvalidArgumentException::class, 'The limit of FilesWithinTokenBudget must be at least 1 token.');
})->with([0, -1]);

it('skips data files', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, str_repeat('a', 20001));

    expect($file->reports(new FilesWithinTokenBudget))->toBe([]);
})->with([
    'public/logo.svg',
    'database/schema/mysql.sql',
    'storage/backup.dump',
    'database/seeders/cities.csv',
    'public/app.min.js',
    'public/app.min.css',
    'UPPER.SVG',
]);
