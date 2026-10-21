<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\NoHttpInUnitTests;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails every request through the test case in a unit test', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', <<<'PHP'
        <?php

        it('stores the order', function () {
            $this->post('/orders');
            $this->getJson('/orders');
            $this->call('DELETE', '/orders/1');
            $this->json('PATCH', '/orders/1');
            $this->putJson('/orders/1');
            $this->optionsJson('/orders');
        });
        PHP);

    expect($file->lines(new NoHttpInUnitTests))->toBe([4, 5, 6, 7, 8, 9]);
});

it("fails a request through Pest's Laravel functions in a unit test", function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', <<<'PHP'
        <?php

        use function Pest\Laravel\get;
        use function Pest\Laravel\postJson;

        it('stores the order', function () {
            get('/orders');
            postJson('/orders');
            \Pest\Laravel\delete('/orders/1');
        });
        PHP);

    expect($file->lines(new NoHttpInUnitTests))->toBe([7, 8, 9]);
});

it('passes calls that make no request', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', <<<'PHP'
        <?php

        it('totals the order', function () {
            $order->get('total');
            $this->app->get('cache');
            $this->postProcess($order);
            get_class($order);
            get('/orders');
            collect($items)->put('total', 5);
        });
        PHP);

    expect($file->lines(new NoHttpInUnitTests))->toBe([]);
});

it('reads only the tests in a Unit folder', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\n\$this->post('/orders');\n");

    expect($file->lines(new NoHttpInUnitTests))->toBe($lines);
})->with([
    'a unit test' => ['tests/Unit/OrderTest.php', Kind::TESTS, [3]],
    'a nested unit test' => ['tests/Unit/Orders/TotalTest.php', Kind::TESTS, [3]],
    'a unit test of a package in a monorepo' => ['packages/billing/tests/Unit/OrderTest.php', Kind::TESTS, [3]],
    'a feature test' => ['tests/Feature/OrderTest.php', Kind::TESTS, []],
    'source code in a Unit folder' => ['app/Unit/Order.php', Kind::SOURCE, []],
]);

it('reads the tests in the folder a subclass names', function () {
    $rule = new class extends NoHttpInUnitTests
    {
        protected string $folder = 'Feature';

        public function message(): string
        {
            return 'Write it as an acceptance test through the app.';
        }
    };

    $feature = TestFile::of(Kind::TESTS, 'tests/Feature/OrderTest.php', "<?php\n\n\$this->postJson('/orders');\n");
    $unit = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', "<?php\n\n\$this->postJson('/orders');\n");

    expect($feature->lines($rule))->toBe([3])
        ->and($unit->lines($rule))->toBe([])
        ->and($rule->scope())->toBe('tests in a Feature folder');
});

it('passes a request whose argument the change edits on another line', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', "<?php\n\n\$this->post(\n    '/orders',\n);\n")
        ->changedFrom("<?php\n\n\$this->post(\n    '/carts',\n);\n");

    expect($file->lines(new NoHttpInUnitTests))->toBe([]);
});
