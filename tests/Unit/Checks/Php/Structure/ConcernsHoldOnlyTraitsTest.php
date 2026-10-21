<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\ConcernsHoldOnlyTraits;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a class, interface or enum in a Concerns folder at any depth', function (string $path) {
    $file = TestFile::of(Kind::SOURCE, $path, <<<'PHP'
        <?php

        class Example {}

        interface Reader {}

        enum Mode {}
        PHP);

    expect($file->lines(new ConcernsHoldOnlyTraits))->toBe([3, 5, 7]);
})->with([
    'Concerns/Example.php',
    'app/Concerns/Example.php',
    'app/Models/Concerns/Example.php',
    'app/Models/Billing/Concerns/Example.php',
    'src/Concerns/Example.php',
]);

it('passes a trait in a Concerns folder', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/Concerns/HasExample.php', "<?php\n\ntrait HasExample {}\n");

    expect($file->lines(new ConcernsHoldOnlyTraits))->toBe([]);
});

it('leaves a class outside a Concerns folder alone', function (string $path) {
    $file = TestFile::of(Kind::SOURCE, $path, "<?php\n\nclass Example {}\n");

    expect($file->lines(new ConcernsHoldOnlyTraits))->toBe([]);
})->with([
    'app/Models/Example.php',
    'app/Concernsy/Example.php',
    'app/MyConcerns/Example.php',
]);

it('leaves a test in a Concerns folder alone, at any path', function (string $path) {
    $file = TestFile::of(Kind::TESTS, $path, "<?php\n\nclass ExampleTest extends TestCase {}\n");

    expect($file->lines(new ConcernsHoldOnlyTraits))->toBe([]);
})->with([
    'tests/Concerns/ExampleTest.php',
    'tests/Unit/Models/Concerns/HasRolesTest.php',
    'packages/billing/tests/Concerns/ExampleTest.php',
]);

it('passes a class whose body the change edits', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/Concerns/Example.php', "<?php\n\nclass Example\n{\n    public int \$a = 2;\n}\n")
        ->changedFrom("<?php\n\nclass Example\n{\n    public int \$a = 1;\n}\n");

    expect($file->lines(new ConcernsHoldOnlyTraits))->toBe([]);
});
