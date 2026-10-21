<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Config\SortedConfigKeys;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a key that sorts before the key above it, at every depth', function () {
    $file = TestFile::of(Kind::CONFIG, 'config/billing.php', <<<'PHP'
        <?php

        return [
            'currency' => 'EUR',
            'api' => [
                'timeout' => 5,
                'key' => 'secret',
                'url' => 'https://example.com',
            ],
            'account' => 'main',
            'Zone' => 'a',
            'alpha' => 'b',
        ];
        PHP);

    expect($file->lines(new SortedConfigKeys))->toBe([5, 7, 10, 11]);
});

it('passes keys in alphabetical order, compared byte by byte', function () {
    $file = TestFile::of(Kind::CONFIG, 'config/billing.php', <<<'PHP'
        <?php

        return [
            'Zone' => 'z',
            'api' => 'a',
            'api_key' => 'k',
            'currency' => 'EUR',
        ];
        PHP);

    expect($file->lines(new SortedConfigKeys))->toBe([]);
});

it('leaves a list, a mix of keyed and unkeyed items and a computed key alone', function () {
    $file = TestFile::of(Kind::CONFIG, 'config/billing.php', <<<'PHP'
        <?php

        return [
            'computed' => [Gateway::STRIPE => 1, Gateway::ADYEN => 2],
            'drivers' => ['stripe', 'adyen', 'mollie'],
            'empty' => [],
            'indexed' => [3 => 'c', 1 => 'a'],
            'mixed' => ['b' => 1, 'x', 'a' => 2],
            'single' => ['only' => 1],
        ];
        PHP);

    expect($file->lines(new SortedConfigKeys))->toBe([]);
});

it('reads the config only, whatever the path', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\nreturn [\n    'b' => 1,\n    'a' => 2,\n];\n");

    expect($file->lines(new SortedConfigKeys))->toBe($lines);
})->with([
    'a config file' => ['config/app.php', Kind::CONFIG, [5]],
    'source code' => ['app/Support/map.php', Kind::SOURCE, []],
    'a test' => ['tests/Feature/ExampleTest.php', Kind::TESTS, []],
    'a route file' => ['routes/web.php', Kind::ROUTES, []],
]);
