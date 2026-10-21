<?php

use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Layout;
use ClaudioDekker\LaravelGuardrails\Project;
use PhpParser\PhpVersion;

it('resolves a stock Laravel app from its composer.json and phpunit.xml', function () {
    $layout = Project::onDisk(__DIR__.'/../Fixtures/skeleton')->layout();

    expect($layout->roots(Kind::SOURCE))->toBe(['app', 'database/factories', 'database/seeders'])
        ->and($layout->roots(Kind::TESTS))->toBe(['tests', 'tests/Unit', 'tests/Feature'])
        ->and($layout->roots(Kind::CONFIG))->toBe(['config'])
        ->and($layout->roots(Kind::MIGRATIONS))->toBe(['database/migrations'])
        ->and($layout->roots(Kind::FRONTEND))->toBe(['resources/js', 'resources/css'])
        ->and($layout->roots(Kind::LANG))->toBe(['lang', 'resources/lang'])
        ->and($layout->roots(Kind::ROUTES))->toBe(['routes'])
        ->and($layout->roots(Kind::VIEWS))->toBe(['resources/views'])
        ->and($layout->roots(Kind::TEXT))->toBe([]);
});

it('tells the kind of each file of a stock Laravel app', function (string $path, Kind $kind) {
    expect(Project::onDisk(__DIR__.'/../Fixtures/skeleton')->layout()->kindOf($path))->toBe($kind);
})->with([
    ['app/Models/User.php', Kind::SOURCE],
    ['database/factories/UserFactory.php', Kind::SOURCE],
    ['database/seeders/DatabaseSeeder.php', Kind::SOURCE],
    ['tests/Feature/ExampleTest.php', Kind::TESTS],
    ['tests/Pest.php', Kind::TESTS],
    ['tests/TestCase.php', Kind::TESTS],
    ['config/app.php', Kind::CONFIG],
    ['database/migrations/0001_01_01_000000_create_users_table.php', Kind::MIGRATIONS],
    ['routes/web.php', Kind::ROUTES],
    ['lang/en/auth.php', Kind::LANG],
    ['resources/lang/en/auth.php', Kind::LANG],
    ['resources/views/app.blade.php', Kind::VIEWS],
    ['resources/js/app.ts', Kind::FRONTEND],
    ['resources/css/app.css', Kind::FRONTEND],
    ['resources/fonts/inter.woff2', Kind::TEXT],
    ['bootstrap/app.php', Kind::TEXT],
    ['README.md', Kind::TEXT],
    ['package.json', Kind::TEXT],
    ['apple.php', Kind::TEXT],
    ['application/Models/User.php', Kind::TEXT],
]);

it('resolves a monorepo whose root composer.json autoloads the packages and their tests', function () {
    $layout = Project::onDisk(__DIR__.'/../Fixtures/monorepo')->layout();

    expect($layout->roots(Kind::SOURCE))->toBe(['packages/billing/src', 'packages/crm/src', 'packages/billing/src/helpers.php'])
        ->and($layout->roots(Kind::TESTS))->toBe(['packages/billing/tests', 'packages/crm/tests'])
        ->and($layout->roots(Kind::CONFIG))->toBe(['config', 'packages/billing/config'])
        ->and($layout->roots(Kind::MIGRATIONS))->toBe(['database/migrations', 'packages/billing/database/migrations'])
        ->and($layout->roots(Kind::ROUTES))->toBe(['routes', 'packages/billing/routes']);
});

it('tells the kind of each file of a monorepo', function (string $path, Kind $kind) {
    expect(Project::onDisk(__DIR__.'/../Fixtures/monorepo')->layout()->kindOf($path))->toBe($kind);
})->with([
    ['packages/billing/src/Invoice.php', Kind::SOURCE],
    ['packages/billing/src/helpers.php', Kind::SOURCE],
    ['packages/crm/src/Contact.php', Kind::SOURCE],
    ['packages/billing/tests/Unit/InvoiceTest.php', Kind::TESTS],
    ['packages/crm/tests/Feature/ContactTest.php', Kind::TESTS],
    ['packages/billing/config/billing.php', Kind::CONFIG],
    ['packages/billing/database/migrations/2026_01_01_000000_create_invoices_table.php', Kind::MIGRATIONS],
    ['packages/billing/routes/web.php', Kind::ROUTES],
    ['packages/billing/bootstrap/app.php', Kind::TEXT],
    ['packages/support/src/Helper.php', Kind::TEXT],
    ['README.md', Kind::TEXT],
]);

it('reads every autoload form, with a wildcard in a folder name, and the config and migrations folders beside each source root that exist', function () {
    $layout = Project::onDisk(projectWith(['packages/shop/config/shop.php' => '<?php', 'packages/shop/src/Cart.php' => '<?php', 'composer.json' => json_encode([
        'autoload' => [
            'psr-4' => ['Acme\\' => ['lib/', 'src/']],
            'psr-0' => ['Legacy_' => 'legacy/'],
            'classmap' => ['packages/*/src', './database/seeds/'],
            'files' => ['app/helpers.php'],
        ],
        'autoload-dev' => ['classmap' => ['packages/*/tests']],
    ], JSON_THROW_ON_ERROR)]))->layout();

    expect($layout->roots(Kind::SOURCE))->toBe(['lib', 'src', 'legacy', 'packages/*/src', 'database/seeds', 'app/helpers.php'])
        ->and($layout->roots(Kind::TESTS))->toBe(['packages/*/tests'])
        ->and($layout->roots(Kind::CONFIG))->toBe(['config', 'packages/*/config'])
        ->and($layout->roots(Kind::MIGRATIONS))->toBe(['database/migrations'])
        ->and($layout->kindOf('packages/shop/src/Cart.php'))->toBe(Kind::SOURCE)
        ->and($layout->kindOf('packages/shop/tests/CartTest.php'))->toBe(Kind::TESTS)
        ->and($layout->kindOf('packages/shop/config/shop.php'))->toBe(Kind::CONFIG)
        ->and($layout->kindOf('packages/shop/nested/src/Cart.php'))->toBe(Kind::TEXT)
        ->and($layout->kindOf('app/helpers.php'))->toBe(Kind::SOURCE)
        ->and($layout->kindOf('app/Models/User.php'))->toBe(Kind::TEXT)
        ->and($layout->kindOf('legacy/Legacy/Thing.php'))->toBe(Kind::SOURCE);
});

it('reads the test suites of phpunit.xml.dist when there is no phpunit.xml, with their files and wildcards, beside the tests folder when composer.json names none', function () {
    $layout = Project::onDisk(projectWith([
        'composer.json' => '{"autoload": {"psr-4": {"App\\\\": "app/"}}}',
        'phpunit.xml.dist' => '<phpunit><testsuites><testsuite name="All"><directory>./packages/*/tests/</directory><file>tests/SmokeTest.php</file></testsuite></testsuites></phpunit>',
    ]))->layout();

    expect($layout->roots(Kind::TESTS))->toBe(['tests', 'packages/*/tests', 'tests/SmokeTest.php'])
        ->and($layout->kindOf('packages/shop/tests/CartTest.php'))->toBe(Kind::TESTS)
        ->and($layout->kindOf('tests/SmokeTest.php'))->toBe(Kind::TESTS)
        ->and($layout->kindOf('tests/OtherTest.php'))->toBe(Kind::TESTS);
});

it('falls back to the Laravel folders when the project declares nothing', function (array $files) {
    $layout = Project::onDisk(projectWith($files))->layout();

    expect($layout->roots(Kind::SOURCE))->toBe(['app', 'src'])
        ->and($layout->roots(Kind::TESTS))->toBe(['tests'])
        ->and($layout->roots(Kind::CONFIG))->toBe(['config'])
        ->and($layout->roots(Kind::MIGRATIONS))->toBe(['database/migrations'])
        ->and($layout->kindOf('app/Models/User.php'))->toBe(Kind::SOURCE)
        ->and($layout->kindOf('src/Check.php'))->toBe(Kind::SOURCE)
        ->and($layout->kindOf('tests/Unit/ExampleTest.php'))->toBe(Kind::TESTS);
})->with([
    'no composer.json' => [[]],
    'no autoload' => [['composer.json' => '{"name": "acme/app"}']],
    'empty autoload' => [['composer.json' => '{"autoload": {"psr-4": {}}, "autoload-dev": {}}']],
]);

it('gives a path under two kinds to the deeper root, and to the tests on a tie', function () {
    $layout = new Layout(source: ['', 'src', 'tests/Support'], tests: ['tests', 'src/Testing'], config: ['config'], migrations: ['database/migrations']);

    expect($layout->kindOf('src/Testing/Fake.php'))->toBe(Kind::TESTS)
        ->and($layout->kindOf('tests/Support/Helper.php'))->toBe(Kind::SOURCE)
        ->and($layout->kindOf('tests/Unit/HelperTest.php'))->toBe(Kind::TESTS)
        ->and($layout->kindOf('src/Check.php'))->toBe(Kind::SOURCE)
        ->and($layout->kindOf('config/app.php'))->toBe(Kind::CONFIG)
        ->and($layout->kindOf('README.md'))->toBe(Kind::SOURCE)
        ->and((new Layout(source: ['app'], tests: ['app']))->kindOf('app/User.php'))->toBe(Kind::TESTS)
        ->and((new Layout(source: ['database'], migrations: ['database/migrations']))->kindOf('database/migrations/0001_create.php'))->toBe(Kind::MIGRATIONS)
        ->and((new Layout(source: ['database'], migrations: ['database/migrations']))->kindOf('database/seeders/Seed.php'))->toBe(Kind::SOURCE);
});

it('targets the lowest PHP version composer.json requires', function (string $constraint, string $version) {
    $layout = Project::onDisk(projectWith(['composer.json' => json_encode(['require' => ['php' => $constraint]], JSON_THROW_ON_ERROR)]))->layout();

    expect($layout->php())->toEqual(PhpVersion::fromString($version));
})->with([
    'a caret' => ['^8.3', '8.3'],
    'a range' => ['>=8.2 <9.0', '8.2'],
    'alternatives' => ['8.3 || 8.1', '8.1'],
    'a patch wildcard' => ['8.2.*', '8.2'],
    'a patch version' => ['>=8.1.2', '8.1'],
    'a tilde' => ['~8.4.0', '8.4'],
]);

it('targets no PHP version when composer.json names none', function (array $files) {
    expect(Project::onDisk(projectWith($files))->layout()->php())->toBeNull();
})->with([
    'no composer.json' => [[]],
    'no require' => [['composer.json' => '{"name": "acme/app"}']],
    'no php' => [['composer.json' => '{"require": {"laravel/framework": "^12.0"}}']],
    'any version' => [['composer.json' => '{"require": {"php": "*"}}']],
    'a major alone' => [['composer.json' => '{"require": {"php": ">=8"}}']],
]);

it('reads the lang, routes, views and frontend folders beside each source root that exist', function () {
    $layout = Project::onDisk(projectWith([
        'composer.json' => '{"autoload": {"psr-4": {"Acme\\\\Shop\\\\": "packages/shop/src/"}}}',
        'packages/shop/src/Cart.php' => '<?php',
        'packages/shop/lang/en/cart.php' => '<?php',
        'packages/shop/resources/lang/nl/cart.php' => '<?php',
        'packages/shop/routes/web.php' => '<?php',
        'packages/shop/resources/views/cart.blade.php' => '',
        'packages/shop/resources/js/cart.ts' => '',
        'packages/shop/resources/css/cart.css' => '',
    ]))->layout();

    expect($layout->roots(Kind::LANG))->toBe(['lang', 'resources/lang', 'packages/shop/lang', 'packages/shop/resources/lang'])
        ->and($layout->roots(Kind::ROUTES))->toBe(['routes', 'packages/shop/routes'])
        ->and($layout->roots(Kind::VIEWS))->toBe(['resources/views', 'packages/shop/resources/views'])
        ->and($layout->roots(Kind::FRONTEND))->toBe(['resources/js', 'resources/css', 'packages/shop/resources/js', 'packages/shop/resources/css'])
        ->and($layout->kindOf('packages/shop/lang/en/cart.php'))->toBe(Kind::LANG)
        ->and($layout->kindOf('packages/shop/routes/web.php'))->toBe(Kind::ROUTES)
        ->and($layout->kindOf('packages/shop/resources/views/cart.blade.php'))->toBe(Kind::VIEWS)
        ->and($layout->kindOf('packages/shop/resources/js/cart.ts'))->toBe(Kind::FRONTEND);
});

it('gives a path under the roots of several kinds at the same depth to the first of tests, migrations, config, lang, routes, views, frontend and source', function () {
    $order = ['tests', 'migrations', 'config', 'lang', 'routes', 'views', 'frontend', 'source'];

    foreach ($order as $index => $first) {
        $roots = array_fill_keys(array_slice($order, $index), ['app']);
        $layout = new Layout(...$roots);

        expect($layout->kindOf('app/File.php'))->toBe(Kind::from($first));
    }
});

it('places every kind but text in the order that settles a path under several roots', function () {
    $remaining = array_map(fn (Kind $kind) => $kind->value, Kind::except(Kind::TEXT));
    $walked = [];

    while ($remaining !== []) {
        $layout = new Layout(...array_fill_keys($remaining, ['app']));
        $first = $layout->kindOf('app/File.php');

        if ($first === Kind::TEXT) {
            break;
        }

        $walked[] = $first->value;
        $remaining = array_values(array_diff($remaining, [$first->value]));
    }

    expect($walked)->toHaveCount(count(Kind::cases()) - 1)
        ->and(array_unique($walked))->toHaveCount(count($walked));
});

it('refuses a composer.json that is not valid JSON', function () {
    expect(fn () => Project::onDisk(projectWith(['composer.json' => '{']))->layout())
        ->toThrow(InvalidArgumentException::class, 'is not valid JSON');
});

it('reads the config and migrations folders inside a source root that is a package root, and none beside the project root', function () {
    $layout = Project::onDisk(projectWith([
        'packages/billing/config/billing.php' => '<?php',
        'packages/billing/database/migrations/.gitkeep' => '',
        'config/app.php' => '<?php',
        'composer.json' => '{"autoload": {"psr-4": {"Acme\\\\Billing\\\\": "packages/billing/"}, "classmap": ["."]}}',
    ]))->layout();

    expect($layout->roots(Kind::CONFIG))->toBe(['config', 'packages/billing/config'])
        ->and($layout->roots(Kind::MIGRATIONS))->toBe(['database/migrations', 'packages/billing/database/migrations'])
        ->and($layout->kindOf('packages/billing/config/billing.php'))->toBe(Kind::CONFIG)
        ->and($layout->kindOf('packages/billing/Invoice.php'))->toBe(Kind::SOURCE);
});

it('matches a root without a wildcard as it is written', function () {
    expect(Layout::holds('app/[x]', 'app/[x]/File.php'))->toBeTrue()
        ->and(Layout::holds('app/[x]', 'app/x/File.php'))->toBeFalse()
        ->and(Layout::holds('app/*', 'app/x/File.php'))->toBeTrue();
});

it('reads the autoload paths of each package a path repository holds, with the config and migrations folders beside them', function () {
    $layout = Project::onDisk(projectWith([
        'composer.json' => json_encode([
            'autoload' => ['psr-4' => ['App\\' => 'app/']],
            'repositories' => [['type' => 'path', 'url' => './packages/*']],
        ], JSON_THROW_ON_ERROR),
        'packages/shop/composer.json' => '{"autoload": {"psr-4": {"Acme\\\\Shop\\\\": "src/"}, "files": ["helpers.php"]}, "autoload-dev": {"psr-4": {"Acme\\\\Shop\\\\Tests\\\\": "tests/"}}}',
        'packages/shop/config/shop.php' => '<?php',
        'packages/shop/database/migrations/.gitkeep' => '',
        'packages/blog/composer.json' => '{"autoload": {"classmap": ["."]}}',
        'packages/empty/README.md' => '',
    ]))->layout();

    expect($layout->roots(Kind::SOURCE))->toBe(['app', 'packages/blog', 'packages/shop/src', 'packages/shop/helpers.php'])
        ->and($layout->roots(Kind::TESTS))->toBe(['tests', 'packages/shop/tests'])
        ->and($layout->roots(Kind::CONFIG))->toBe(['config', 'packages/shop/config'])
        ->and($layout->roots(Kind::MIGRATIONS))->toBe(['database/migrations', 'packages/shop/database/migrations'])
        ->and($layout->kindOf('packages/shop/src/Cart.php'))->toBe(Kind::SOURCE)
        ->and($layout->kindOf('packages/shop/tests/Unit/CartTest.php'))->toBe(Kind::TESTS)
        ->and($layout->kindOf('packages/shop/config/shop.php'))->toBe(Kind::CONFIG)
        ->and($layout->kindOf('packages/shop/database/migrations/2026_01_01_000000_create_carts_table.php'))->toBe(Kind::MIGRATIONS)
        ->and($layout->kindOf('packages/blog/Post.php'))->toBe(Kind::SOURCE)
        ->and($layout->kindOf('packages/empty/README.md'))->toBe(Kind::TEXT);
});

it('names a path once when the root composer.json and a path repository both autoload it', function () {
    $layout = Project::onDisk(projectWith([
        'composer.json' => '{"autoload": {"psr-4": {"Acme\\\\Shop\\\\": "packages/shop/src/"}}, "repositories": [{"type": "path", "url": "packages/shop"}]}',
        'packages/shop/composer.json' => '{"autoload": {"psr-4": {"Acme\\\\Shop\\\\": "src/"}}}',
    ]))->layout();

    expect($layout->roots(Kind::SOURCE))->toBe(['packages/shop/src']);
});

it('ignores a path repository outside the project', function (string $url) {
    $root = projectWith(['sibling/composer.json' => '{"autoload": {"psr-4": {"Acme\\\\": "src/"}}}']);
    $url = str_replace('{root}', $root, $url);

    mkdir($root.'/app');
    file_put_contents($root.'/app/composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}, "repositories": [{"type": "path", "url": "'.$url.'"}]}');

    $layout = Project::onDisk($root.'/app')->layout();

    expect($layout->roots(Kind::SOURCE))->toBe(['app']);
})->with([
    'a parent path' => '../sibling',
    'an absolute path' => '{root}/sibling',
]);

it('ignores a repository that is not a path repository', function () {
    $layout = Project::onDisk(projectWith([
        'composer.json' => '{"autoload": {"psr-4": {"App\\\\": "app/"}}, "repositories": [{"type": "vcs", "url": "packages/shop"}, {"type": "composer", "url": "https://packages.example.com"}, "packagist.org"]}',
        'packages/shop/composer.json' => '{"autoload": {"psr-4": {"Acme\\\\Shop\\\\": "src/"}}}',
    ]))->layout();

    expect($layout->roots(Kind::SOURCE))->toBe(['app']);
});

it('reads the namespaces autoload-dev maps in the project and in each path repository, each ending in one backslash', function () {
    $layout = Project::onDisk(projectWith([
        'composer.json' => json_encode([
            'autoload-dev' => [
                'psr-4' => ['Acme\\Testing\\' => 'tests/', '\\Acme\\Support' => 'support/'],
                'psr-0' => ['Legacy_' => 'legacy/'],
                'classmap' => ['database/'],
            ],
            'repositories' => [['type' => 'path', 'url' => 'packages/*']],
        ], JSON_THROW_ON_ERROR),
        'packages/shop/composer.json' => '{"autoload-dev": {"psr-4": {"Acme\\\\Shop\\\\Tests\\\\": "tests/", "Acme\\\\Testing\\\\": "tests/"}}, "autoload": {"psr-4": {"Acme\\\\Shop\\\\": "src/"}}}',
    ]))->layout();

    expect($layout->devNamespaces())->toBe(['Acme\\Testing\\', 'Acme\\Support\\', 'Legacy_\\', 'Acme\\Shop\\Tests\\']);
});

it('maps no development namespace when nothing in autoload-dev names one', function (array $files) {
    expect(Project::onDisk(projectWith($files))->layout()->devNamespaces())->toBe([]);
})->with([
    'no composer.json' => [[]],
    'no autoload-dev' => [['composer.json' => '{"autoload": {"psr-4": {"App\\\\": "app/"}}}']],
    'a classmap only' => [['composer.json' => '{"autoload-dev": {"classmap": ["tests/"]}}']],
]);
