# Test a check

`Testing\TestFile` runs a check over a file you write in a test, with no repository and no `guardrails.php`. Most examples test `NoDateFunctions`, the check that [Write a check](writing-checks.md) builds.

`TestFile::of()` takes the kind, the path and the contents, and `reports()` returns what a run would report: the line and the message of each violation, with a `null` line for a violation of the whole file. The list holds nothing else, so a field added to `Violation` later cannot break your tests.

```php
use Tests\Guardrails\NoDateFunctions;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('flags each call to date() and time()', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Billing/Invoice.php', "<?php\n\n\$a = date('Y');\n\$b = time();\n");

    expect($file->reports(new NoDateFunctions))->toEqual([
        ['line' => 3, 'message' => 'Replace date() with now().'],
        ['line' => 4, 'message' => 'Replace time() with now().'],
    ]);
});
```

`lines()` returns only the lines, for when the message does not matter:

```php
expect($file->lines(new NoDateFunctions))->toBe([3, 4]);
```

Both report what a run would. Contents that do not parse give no violations to a check that parses them, because a run leaves such a file to `NoSyntaxErrors`. A violation without a message, from a check without `message()`, throws the `LogicException` that a run reports as a crash of the check.

A file you give only to `of()` is an added file, so every violation counts. To test what a change brings in, say what the file was before. `changedFrom()` takes the earlier contents, and only the violations the changed lines bring in remain:

```php
it('flags only the call the change adds', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Billing/Invoice.php', "<?php\n\ndate('Y');\ntime();\n")
        ->changedFrom("<?php\n\ndate('Y');\n");

    expect($file->reports(new NoDateFunctions))->toEqual([['line' => 4, 'message' => 'Replace time() with now().']]);
});
```

`movedFrom()` takes the old path, for a rule about where a file lives:

```php
it('flags a test moved out of the Feature folder', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Other/FooTest.php', "<?php\n")
        ->movedFrom('tests/Feature/FooTest.php');

    expect($file->reports(new TestsInUnitOrFeature))->toBe([['line' => null, 'message' => 'Move the test into a Unit or Feature folder.']]);
});
```

`movedFrom()` also takes the old file's kind, for a rule that depends on it: `movedFrom('docs/FooTest.php', Kind::TEXT)` moves the file in from another kind.

`deleted()` makes the change one that deletes the file. This test of `NoEditedMigrations`, the check in [Judge the change itself](judging-changes.md), covers each kind of change:

```php
it('fails an edit, a move and a deletion of a committed migration', function () {
    $migration = TestFile::of(Kind::MIGRATIONS, 'database/migrations/2026_01_01_000000_create_users_table.php', "<?php\n\nreturn 2;\n");

    $expected = [['line' => null, 'message' => 'Add a new migration instead, because this one may already have run.']];

    expect($migration->changedFrom("<?php\n\nreturn 1;\n")->reports(new NoEditedMigrations))->toBe($expected)
        ->and($migration->movedFrom('database/migrations/2025_01_01_000000_create_users_table.php')->reports(new NoEditedMigrations))->toBe($expected)
        ->and($migration->deleted()->reports(new NoEditedMigrations))->toBe($expected)
        ->and($migration->reports(new NoEditedMigrations))->toBe([]);
});
```

A check that reads `phpunit.xml` or `composer.json` needs the project's folder. `in()` takes it, and also sets the PHP version the file is parsed for. Here the `phpunit.xml` of the folder runs `tests/Unit` and `tests/Feature`:

```php
it('flags a test outside every test suite', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Http/FooTest.php', "<?php\n")->in(__DIR__.'/Fixtures/project');

    expect($file->reports(new TestsInTestSuites))->toEqual([
        ['line' => null, 'message' => 'Move the test into tests/Unit or tests/Feature, or add its folder to a test suite in phpunit.xml.'],
    ]);
});
```
