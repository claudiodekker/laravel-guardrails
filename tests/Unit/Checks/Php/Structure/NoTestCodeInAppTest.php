<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\NoTestCodeInApp;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails every use of the Tests namespace, imported or fully qualified', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Providers/AppServiceProvider.php', <<<'PHP'
        <?php

        namespace App\Providers;

        use Illuminate\Support\ServiceProvider;
        use Tests\Support\FakeClock;
        use function Tests\freeze;

        class AppServiceProvider extends ServiceProvider
        {
            public function register(): void
            {
                $this->app->bind(Clock::class, FakeClock::class);
                $this->app->bind(Clock::class, \Tests\Support\OtherClock::class);
                freeze();
                $name = 'Tests\\Support\\FakeClock';
                $this->testsRun();
            }
        }
        PHP);

    expect($file->lines(new NoTestCodeInApp))->toBe([6, 7, 13, 14, 15]);
});

it('reads every kind of code but the tests, whatever the path', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\nuse Tests\\TestCase;\n");

    expect($file->lines(new NoTestCodeInApp))->toBe($lines);
})->with([
    'source code' => ['app/Models/User.php', Kind::SOURCE, [3]],
    'a migration' => ['database/migrations/2026_01_01_000000_create_users_table.php', Kind::MIGRATIONS, [3]],
    'a config file' => ['config/app.php', Kind::CONFIG, [3]],
    'a route file' => ['routes/console.php', Kind::TEXT, [3]],
    'a test' => ['tests/Feature/ExampleTest.php', Kind::TESTS, []],
    'a test beside the source code' => ['app/Testing/ExampleTest.php', Kind::TESTS, []],
]);

it('fails a name under a namespace the project maps in autoload-dev, and leaves other names alone', function () {
    $root = projectWith(['composer.json' => '{"autoload-dev": {"psr-4": {"Acme\\\\Testing\\\\": "tests/"}}}']);
    $file = TestFile::of(Kind::SOURCE, 'app/Providers/AppServiceProvider.php', <<<'PHP'
        <?php

        use Acme\Testing\FakeClock;
        use Acme\Testingly\Other;
        use Tests\Support\Helper;

        $clock = new FakeClock;
        PHP)->in($root);

    expect($file->lines(new NoTestCodeInApp))->toBe([3, 7]);
});

it('reads the namespaces of the path repositories of the project too', function () {
    $root = projectWith([
        'composer.json' => '{"repositories": [{"type": "path", "url": "packages/*"}]}',
        'packages/shop/composer.json' => '{"autoload-dev": {"psr-4": {"Acme\\\\Shop\\\\Testing\\\\": "tests/"}}}',
    ]);

    $file = TestFile::of(Kind::SOURCE, 'app/Cart.php', "<?php\n\nuse Acme\\Shop\\Testing\\FakeCart;\n")->in($root);

    expect($file->lines(new NoTestCodeInApp))->toBe([3]);
});

it('falls back to the Tests namespace when the project maps no namespace in autoload-dev', function (string $root) {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\nuse Tests\\TestCase;\nuse Acme\\Testing\\FakeClock;\n")->in($root);

    expect($file->lines(new NoTestCodeInApp))->toBe([3]);
})->with([
    'a project without composer.json' => fn () => projectWith([]),
    'a project that maps nothing' => fn () => projectWith(['composer.json' => '{"autoload-dev": {"classmap": ["tests/"]}}']),
]);

it('falls back to the Tests namespace for a file from no project', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\nuse Tests\\TestCase;\nuse Acme\\Testing\\FakeClock;\n");

    expect($file->lines(new NoTestCodeInApp))->toBe([3]);
});

it('does not read a stub template', function () {
    $file = TestFile::of(Kind::TEXT, 'stubs/provider.stub.php', "<?php\n\nuse Tests\\Support\\FakeClock;\n");

    expect($file->lines(new NoTestCodeInApp))->toBe([]);
});
