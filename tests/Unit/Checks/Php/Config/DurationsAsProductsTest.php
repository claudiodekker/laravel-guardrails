<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Config\DurationsAsProducts;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a duration in seconds that is a whole number of minutes', function () {
    $file = TestFile::of(Kind::CONFIG, 'config/billing.php', <<<'PHP'
        <?php

        return [
            'lifetime_seconds' => 43200,
            'window_seconds' => 120,
            'retry' => [
                'backoff_seconds' => 3600,
            ],
        ];
        PHP);

    expect($file->lines(new DurationsAsProducts))->toBe([4, 5, 7]);
});

it('passes a duration written as a product, a single minute, seconds that are not whole minutes and a key that is not in seconds', function () {
    $file = TestFile::of(Kind::CONFIG, 'config/billing.php', <<<'PHP'
        <?php

        return [
            'cooldown_seconds' => 60,
            'grace_seconds' => 45,
            'lifetime_seconds' => 12 * 60 * 60,
            'odd_seconds' => 90,
            'retry' => 3600,
            'lifetime' => 120,
        ];
        PHP);

    expect($file->lines(new DurationsAsProducts))->toBe([]);
});

it('reads only the config files', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\nreturn ['lifetime_seconds' => 43200];\n");

    expect($file->lines(new DurationsAsProducts))->toBe($lines);
})->with([
    'a config file' => ['config/billing.php', Kind::CONFIG, [3]],
    'the config of a package in a monorepo' => ['packages/billing/config/billing.php', Kind::CONFIG, [3]],
    'application code' => ['app/Services/Billing.php', Kind::SOURCE, []],
    'a test' => ['tests/Unit/BillingTest.php', Kind::TESTS, []],
    'a file that looks like config' => ['config/billing.php', Kind::TEXT, []],
]);

it('fails a duration whose value the change edits on a line of its own', function () {
    $file = TestFile::of(Kind::CONFIG, 'config/billing.php', "<?php\n\nreturn [\n    'lifetime_seconds' =>\n        43200,\n];\n")
        ->changedFrom("<?php\n\nreturn [\n    'lifetime_seconds' =>\n        45,\n];\n");

    expect($file->lines(new DurationsAsProducts))->toBe([4]);
});
