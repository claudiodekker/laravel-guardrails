<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Config\NoConfigDefaults;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a config read with an inline default', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Billing.php', <<<'PHP'
        <?php

        use Illuminate\Support\Facades\Config;

        config('billing.limit', 5);
        config()->get('billing.limit', 5);
        config()->string('billing.name', 'Billing');
        Config::integer('billing.limit', 5);
        \Config::get('billing.limit', 5);
        Config::boolean('billing.enabled', false);
        PHP);

    expect($file->lines(new NoConfigDefaults))->toBe([5, 6, 7, 8, 9, 10]);
});

it('passes a config read without a default, a config write and other reads with a default', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Billing.php', <<<'PHP'
        <?php

        use Illuminate\Support\Facades\Config;

        config('billing.limit');
        config(['billing.limit' => 5]);
        config()->get('billing.limit');
        config()->set('billing.limit', 5);
        Config::get('billing.limit');
        Config::set('billing.limit', 5);
        $settings->get('billing.limit', 5);
        $request->input('limit', 5);
        Cache::get('billing.limit', 5);
        PHP);

    expect($file->lines(new NoConfigDefaults))->toBe([]);
});

it('reads every PHP file', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, "<?php\n\nconfig('billing.limit', 5);\n");

    expect($file->lines(new NoConfigDefaults))->toBe([3]);
})->with([
    'application code' => 'app/Services/Billing.php',
    'a route file' => 'routes/web.php',
    'a test' => 'tests/Feature/BillingTest.php',
]);

it('fails a default the change adds on a line of its own', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Billing.php', "<?php\n\n\$limit = config(\n    'app.limit',\n    5,\n);\n")
        ->changedFrom("<?php\n\n\$limit = config(\n    'app.limit',\n);\n");

    expect($file->lines(new NoConfigDefaults))->toBe([3]);
});
