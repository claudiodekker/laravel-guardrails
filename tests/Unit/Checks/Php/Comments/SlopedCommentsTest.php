<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Comments\SlopedComments;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails an inline comment of several lines that is not a slope', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        // We will check the first line here, which is long,
        // and the second line is about as long as the first.
        $a = 1;

        // A comment that runs over three lines
        // gets shorter on every line it has,
        // but then it keeps on going for a
        // fourth line.
        $b = 2;
        PHP);

    expect($file->lines(new SlopedComments))->toBe([3, 7]);
});

it('leaves config files alone, which comment out the options they offer, and reads the rest', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, <<<'PHP'
        <?php

        return [
            'postmark' => [
                'transport' => 'postmark',
                // We will check the first line here, which is long,
                // and the second line is about as long as the first.
            ],
        ];
        PHP);

    expect($file->lines(new SlopedComments))->toBe($lines);
})->with([
    'a config file' => ['config/mail.php', Kind::CONFIG, []],
    'the config of an app in a subfolder' => ['backend/config/mail.php', Kind::CONFIG, []],
    'the config of a fixture app in the tests' => ['tests/Fixtures/skeleton/config/mail.php', Kind::TESTS, []],
    'a file in a configs folder' => ['docs/configs/mail.php', Kind::TEXT, [6]],
    'source code' => ['app/Support/mail.php', Kind::SOURCE, [6]],
]);

it('passes single lines and slopes, and leaves docblocks alone', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        /**
         * Hold the invoice until the payment has cleared.
         *
         * It waits with the app's clock and sleeps with Sleep, so a faked sleep moves
         * a faked clock by the whole wait.
         */
        function hold() {
            // We will check the first line here.
            $a = 1;

            // We will run the slope comment now,
            // which shrinks on each next line,
            // until it reaches the last one.
            $b = 2;
        }
        PHP);

    expect($file->lines(new SlopedComments))->toBe([]);
});

it('fails a paragraph of a block comment that is not a slope when asked for banners, at the line the paragraph starts', function () {
    $file = TestFile::of(Kind::CONFIG, 'config/app.php', <<<'PHP'
        <?php

        return [

            /*
            |--------------------------------------------------------------------------
            | Application Name
            |--------------------------------------------------------------------------
            |
            | This value is the name of your application, which is used
            | when the framework needs to place the application's name
            | in a notification or any other location as required by the
            | application or its packages.
            |
            | A second paragraph can be as long as it likes, but it
            | should still shrink on each line until it ends.
            |
            | We will check that the slope shrinks on each line, ok
            | We will check that the slope shrinks on each line,
            | We will check that the slope shrinks on each l
            |
            */

            'name' => 'Acme',

        ];
        PHP);

    expect($file->lines(new SlopedComments(banners: true)))->toBe([10, 15])
        ->and($file->lines(new SlopedComments))->toBe([]);
});

it('passes a one-line paragraph, a rule and a docblock in a banner', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        /*
        |--------------------------------------------------------------------------
        | Invoices
        |--------------------------------------------------------------------------
        */

        /**
         * | Not a banner, because it is a docblock.
         * | Two lines that do not shrink at all.
         */
        function invoices() {}
        PHP);

    expect($file->lines(new SlopedComments(banners: true)))->toBe([]);
});

it('reads every kind of file when asked for banners, and the config folders only then', function (bool $banners, array $lines) {
    $file = TestFile::of(Kind::CONFIG, 'config/mail.php', <<<'PHP'
        <?php

        return [
            // We will keep the first line of this comment long,
            // and the second line is about as long as the first.
        ];
        PHP);

    expect($file->lines(new SlopedComments(banners: $banners)))->toBe($lines);
})->with([
    'with banners' => [true, [4]],
    'without' => [false, []],
]);

it('leaves a list of supported values at the end of a banner paragraph alone', function () {
    $file = TestFile::of(Kind::CONFIG, 'config/cache.php', <<<'PHP'
        <?php

        return [

            /*
            |--------------------------------------------------------------------------
            | Cache Stores
            |--------------------------------------------------------------------------
            |
            | Here you may define all of the cache stores for your
            | application as well as their drivers. You may even
            | define several stores for one driver if needed
            |
            | Supported drivers: "array", "database", "file", "memcached",
            |                    "redis", "dynamodb", "storage", "octane",
            |                    "session", "failover", "null"
            |
            | Here you may define all of the cache stores for your
            | application as well as their drivers. You may even
            | define several stores for one driver if needed
            | Supported: "array", "database", "file"
            |
            */

        ];
        PHP);

    expect($file->lines(new SlopedComments(banners: true)))->toBe([]);
});

it('still fails the prose before a list of supported values', function () {
    $file = TestFile::of(Kind::CONFIG, 'config/cache.php', <<<'PHP'
        <?php

        return [

            /*
            |--------------------------------------------------------------------------
            | Cache Stores
            |--------------------------------------------------------------------------
            |
            | Here you may define all of the cache stores for your
            | application as well as their drivers. You may even
            | define several stores for one driver and the rest, too.
            | Supported: "array", "database", "file"
            |
            */

        ];
        PHP);

    expect($file->lines(new SlopedComments(banners: true)))->toBe([10]);
});

it('leaves commented-out options alone, with banners or without', function (string $comment) {
    $file = TestFile::of(Kind::SOURCE, 'app/Example.php', "<?php\n\nreturn [\n{$comment}\n];\n");

    expect($file->lines(new SlopedComments(banners: true)))->toBe([])
        ->and($file->lines(new SlopedComments))->toBe([]);
})->with([
    'an array option' => ["    // 'users' => [\n    //     'driver' => 'database',\n    //     'table' => 'users',\n    // ],"],
    'two options' => ["    // 'encrypt' => env('DB_ENCRYPT', 'yes'),\n    // 'trust' => env('DB_TRUST', 'false'),"],
    'a statement' => ["    // \$first = 1;\n    // \$second = 2;"],
]);

it('leaves a banner paragraph of commented-out options alone', function () {
    $file = TestFile::of(Kind::CONFIG, 'config/mail.php', <<<'PHP'
        <?php

        return [

            /*
            |--------------------------------------------------------------------------
            | Mailers
            |--------------------------------------------------------------------------
            |
            | 'smtp' => [
            |     'host' => 'localhost',
            | ],
            |
            */

        ];
        PHP);

    expect($file->lines(new SlopedComments(banners: true)))->toBe([]);
});

it('says which files it reads', function (bool $banners, string $scope) {
    expect((new SlopedComments(banners: $banners))->scope())->toBe($scope);
})->with([
    'with banners' => [true, 'PHP'],
    'without' => [false, 'PHP outside config folders'],
]);

it('names its banners option at the end of its explanation unless banners are on', function () {
    expect((new SlopedComments)->explanation())->toEndWith('Pass banners: true to hold the paragraphs of a /* */ banner to the slope too.')
        ->and((new SlopedComments(banners: true))->explanation())->not->toContain('Pass banners: true')
        ->and((new SlopedComments(banners: true))->explanation())->toContain('Supported: "a", "b"')->toContain('commented-out code');
});

it('fails a comment of several lines the change edits below its first line, at the first line', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', "<?php\n\n// We will check the first line here, which is long,\n// and the second line is about as long as the second.\n\$a = 1;\n")
        ->changedFrom("<?php\n\n// We will check the first line here, which is long,\n// and the second line is about as long as the first.\n\$a = 1;\n");

    expect($file->lines(new SlopedComments))->toBe([3]);
});

it('counts only the // comments that start their own line, so a comment after code ends a run', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Example.php', <<<'PHP'
        <?php

        $a = 1; // We will check the first line here, which is long,
        $b = 2; // and the second line is about as long as the first.
        $c = 3;

        // We will check the first line here, which is long,
        $d = 4; // and the second line is about as long as the first.
        PHP);

    expect($file->lines(new SlopedComments))->toBe([]);
});

it('still fails a run of comments that start their own line after a comment after code', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Example.php', <<<'PHP'
        <?php

        $a = 1; // We will check the first line here, which is long,
        // and the second line is about as long as the first,
        // and the third line is about as long as the second.
        PHP);

    expect($file->lines(new SlopedComments))->toBe([4]);
});
