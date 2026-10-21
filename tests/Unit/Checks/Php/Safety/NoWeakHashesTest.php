<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoWeakHashes;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails md5() and sha1()', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/Signature.php', <<<'PHP'
        <?php

        md5($value);
        \sha1($value);
        MD5($value);
        hash('sha256', $value);
        hash_hmac('sha256', $value, $key);
        $digest->md5();
        Hash::make($value);
        PHP);

    expect($file->lines(new NoWeakHashes))->toBe([3, 4, 5]);
});

it('reads every kind of code but the tests, whatever the path', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\nmd5(\$value);\n");

    expect($file->lines(new NoWeakHashes))->toBe($lines);
})->with([
    'source code' => ['app/Support/Signature.php', Kind::SOURCE, [3]],
    'a route file' => ['routes/web.php', Kind::ROUTES, [3]],
    'a config file' => ['config/app.php', Kind::CONFIG, [3]],
    'a migration' => ['database/migrations/2026_01_01_000000_create_orders_table.php', Kind::MIGRATIONS, [3]],
    'a test' => ['tests/Unit/SignatureTest.php', Kind::TESTS, []],
    'a test beside the source code' => ['app/Testing/Example.php', Kind::TESTS, []],
]);

it('passes md5() whose argument the change edits on another line', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/Signature.php', "<?php\n\nmd5(\n    \$b\n);\n")
        ->changedFrom("<?php\n\nmd5(\n    \$a\n);\n");

    expect($file->lines(new NoWeakHashes))->toBe([]);
});

it('skips the factories and seeders, which make fake data', function (string $path) {
    $file = TestFile::of(Kind::MIGRATIONS, $path, "<?php\n\nmd5('a');\n");

    expect($file->lines(new NoWeakHashes))->toBe([]);
})->with([
    'database/factories/UserFactory.php',
    'database/seeders/UserSeeder.php',
    'packages/shop/database/factories/UserFactory.php',
]);
