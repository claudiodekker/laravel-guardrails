<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoWeakRandomness;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails rand(), mt_rand() and uniqid()', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/Token.php', <<<'PHP'
        <?php

        rand(1, 10);
        mt_rand(1, 10);
        \uniqid();
        random_int(1, 10);
        random_bytes(16);
        Str::random(40);
        $dice->rand();
        PHP);

    expect($file->lines(new NoWeakRandomness))->toBe([3, 4, 5]);
});

it('reads every kind of code but the tests, whatever the path', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\nrand(1, 10);\n");

    expect($file->lines(new NoWeakRandomness))->toBe($lines);
})->with([
    'source code' => ['app/Support/Token.php', Kind::SOURCE, [3]],
    'a route file' => ['routes/web.php', Kind::ROUTES, [3]],
    'a config file' => ['config/app.php', Kind::CONFIG, [3]],
    'a migration' => ['database/migrations/2026_01_01_000000_create_orders_table.php', Kind::MIGRATIONS, [3]],
    'a test' => ['tests/Feature/TokenTest.php', Kind::TESTS, []],
    'a test beside the source code' => ['app/Testing/Example.php', Kind::TESTS, []],
]);

it('passes rand() whose arguments the change edits on another line', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/Token.php', "<?php\n\nrand(\n    1,\n    20,\n);\n")
        ->changedFrom("<?php\n\nrand(\n    1,\n    10,\n);\n");

    expect($file->lines(new NoWeakRandomness))->toBe([]);
});

it('skips the factories and seeders, which make fake data', function (string $path) {
    $file = TestFile::of(Kind::MIGRATIONS, $path, "<?php\n\nrand();\n");

    expect($file->lines(new NoWeakRandomness))->toBe([]);
})->with([
    'database/factories/UserFactory.php',
    'database/seeders/UserSeeder.php',
    'packages/shop/database/factories/UserFactory.php',
]);
