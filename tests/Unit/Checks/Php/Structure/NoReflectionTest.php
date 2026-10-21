<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\NoReflection;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails the Reflection classes and the framework\'s Reflector, however they are written', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Inspector.php', <<<'PHP'
        <?php

        namespace App\Services;

        use Illuminate\Support\Reflector;

        class Inspector
        {
            public function inspect(object $object): array
            {
                $class = new \ReflectionClass($object);
                $method = new \ReflectionMethod($object, 'handle');
                $enum = \ReflectionEnum::class;

                return [Reflector::isCallable($object), $class, $method, $enum];
            }
        }
        PHP);

    expect($file->lines(new NoReflection))->toBe([5, 11, 12, 13, 15]);
});

it('leaves other names alone, including ones that merely start with Reflection', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Inspector.php', <<<'PHP'
        <?php

        use App\ReflectionHelper;
        use Illuminate\Support\Reflectors;
        use Illuminate\Support\Str;

        $helper = new ReflectionHelper;
        $reflection = 'ReflectionClass';
        PHP);

    expect($file->lines(new NoReflection))->toBe([]);
});

it('reads every kind of code but the tests', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\n\$class = new ReflectionClass(\$object);\n");

    expect($file->lines(new NoReflection))->toBe($lines);
})->with([
    'source code' => ['app/Support/Inspector.php', Kind::SOURCE, [3]],
    'a migration' => ['database/migrations/2026_01_01_000000_create_users_table.php', Kind::MIGRATIONS, [3]],
    'a config file' => ['config/app.php', Kind::CONFIG, [3]],
    'a route file' => ['routes/console.php', Kind::TEXT, [3]],
    'a test' => ['tests/Feature/ExampleTest.php', Kind::TESTS, []],
]);
