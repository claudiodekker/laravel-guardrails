<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails every debug function and method call', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Http/Controllers/OrderController.php', <<<'PHP'
        <?php

        namespace App\Http\Controllers;

        class OrderController
        {
            public function store(): void
            {
                dd($order);
                ddd($order);
                dump($order);
                ray($order);
                var_dump($order);
                print_r($order, false);
                \dd($order);
                $order->dd();
                $order?->ddd();
                collect($items)->dump()->all();
                $this->dumpIt($order);
                $order->print_r();
                $this->log('dd');
            }
        }
        PHP);

    expect($file->lines(new NoDebugCalls))->toBe([9, 10, 11, 12, 13, 14, 15, 16, 17, 18]);
});

it('reads every kind of code but the tests, whatever the path', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\ndd(1);\n");

    expect($file->lines(new NoDebugCalls))->toBe($lines);
})->with([
    'source code' => ['app/Support/Debug.php', Kind::SOURCE, [3]],
    'a route file' => ['routes/web.php', Kind::ROUTES, [3]],
    'a config file' => ['config/app.php', Kind::CONFIG, [3]],
    'a migration' => ['database/migrations/2026_01_01_000000_create_orders_table.php', Kind::MIGRATIONS, [3]],
    'a test' => ['tests/Feature/OrderTest.php', Kind::TESTS, []],
    'a test helper' => ['tests/Pest.php', Kind::TESTS, []],
    'a test beside the source code' => ['app/Testing/Debug.php', Kind::TESTS, []],
]);

it('passes a debug call whose argument the change edits on another line', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/Debug.php', "<?php\n\ndd(\n    \$b,\n);\n")
        ->changedFrom("<?php\n\ndd(\n    \$a,\n);\n");

    expect($file->lines(new NoDebugCalls))->toBe([]);
});

it('skips the factories and seeders, which make fake data', function (string $path) {
    $file = TestFile::of(Kind::MIGRATIONS, $path, "<?php\n\ndd(1);\n");

    expect($file->lines(new NoDebugCalls))->toBe([]);
})->with([
    'database/factories/UserFactory.php',
    'database/seeders/UserSeeder.php',
    'packages/shop/database/factories/UserFactory.php',
]);

it('passes print_r and var_export called to return a string', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/Debug.php', <<<'PHP'
        <?php

        $a = print_r($order, true);
        $b = var_export($order, true);
        $c = print_r($order, return: true);
        $d = print_r($order, false);
        print_r($order);
        PHP);

    expect($file->lines(new NoDebugCalls))->toBe([6, 7]);
});
