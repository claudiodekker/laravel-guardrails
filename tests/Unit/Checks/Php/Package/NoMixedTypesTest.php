<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Package\NoMixedTypes;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails mixed as a parameter, promoted property, return or property type', function () {
    $file = TestFile::of(Kind::SOURCE, 'src/Http/Middleware/RefuseCrossSiteRequests.php', <<<'PHP'
        <?php

        class RefuseCrossSiteRequests
        {
            public mixed $last;

            public function __construct(
                protected mixed $settings,
            ) {
                //
            }

            protected function tokenFrom(Request $request): mixed
            {
                return array_map(fn (mixed $value): MIXED => $value, $request->all());
            }
        }

        function decode(mixed $value): string
        {
            return (string) $value;
        }
        PHP);

    expect($file->lines(new NoMixedTypes))->toBe([5, 8, 13, 15, 19]);
});

it('passes precise types, a mixed docblock and a class named like mixed', function () {
    $file = TestFile::of(Kind::SOURCE, 'src/Recorder.php', <<<'PHP'
        <?php

        class Recorder
        {
            /**
             * @param  mixed  $value
             */
            public function record(string|int|null $value, Mixed\Value $other): ?string
            {
                $mixed = 'mixed';

                return null;
            }
        }
        PHP);

    expect($file->lines(new NoMixedTypes))->toBe([]);
});

it('names the fix', function () {
    $file = TestFile::of(Kind::SOURCE, 'src/Recorder.php', "<?php\n\nfunction decode(): mixed\n{\n    return null;\n}\n");

    expect($file->reports(new NoMixedTypes))->toEqual([
        ['line' => 3, 'message' => 'Replace mixed with the types the value can hold, as a union when it is more than one.'],
    ]);
});

it('covers src by default, or the folders it is given, and never the tests', function (string $path, Kind $kind, array $folders, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\nfunction decode(mixed \$value): void\n{\n    //\n}\n");

    expect($file->lines(new NoMixedTypes(...$folders)))->toBe($lines);
})->with([
    'a function in src' => ['src/helpers.php', Kind::SOURCE, [], [3]],
    'a function outside src' => ['app/helpers.php', Kind::SOURCE, [], []],
    'a function in the folder an app gives' => ['app/Billing/helpers.php', Kind::SOURCE, [['app/Billing']], [3]],
    'a function in a package of a monorepo' => ['packages/keystone/src/helpers.php', Kind::SOURCE, [['packages/*/src']], [3]],
    'a test in src' => ['src/HelpersTest.php', Kind::TESTS, [], []],
]);
