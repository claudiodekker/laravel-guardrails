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

it('passes mixed where the docblock names a template type', function (string $source) {
    $file = TestFile::of(Kind::SOURCE, 'src/Recorder.php', "<?php\n\n".$source);

    expect($file->lines(new NoMixedTypes))->toBe([]);
})->with([
    'a template of the method on the return' => [<<<'PHP'
        class Floor
        {
            /**
             * @template TResult
             *
             * @param  Closure(): TResult  $work
             * @return TResult
             */
            public function hold(Closure $work): mixed
            {
                return $work();
            }
        }
        PHP],
    'a template of the class on the return' => [<<<'PHP'
        /**
         * @template TValue
         */
        class Box
        {
            /**
             * @return TValue
             */
            public function get(): mixed
            {
                return null;
            }
        }
        PHP],
    'a template of the interface on a parameter' => [<<<'PHP'
        /**
         * @phpstan-template TValue
         */
        interface Sink
        {
            /**
             * @param  TValue  $value
             */
            public function put(mixed $value): void;
        }
        PHP],
    'a template of the trait declared for psalm' => [<<<'PHP'
        /**
         * @psalm-template TValue
         */
        trait Holds
        {
            /**
             * @return TValue
             */
            public function value(): mixed
            {
                return null;
            }
        }
        PHP],
    'a template on a promoted property' => [<<<'PHP'
        /**
         * @template TValue
         */
        class Box
        {
            /**
             * @param  TValue  $value
             */
            public function __construct(
                protected mixed $value,
            ) {
                //
            }
        }
        PHP],
    'a template on a property' => [<<<'PHP'
        /**
         * @template TValue
         */
        class Box
        {
            /**
             * @var TValue
             */
            protected mixed $value;
        }
        PHP],
    'a template or null' => [<<<'PHP'
        /**
         * @template TValue
         */
        class Box
        {
            /**
             * @return TValue|null
             */
            public function find(): mixed
            {
                return null;
            }
        }
        PHP],
    'a union of two templates' => [<<<'PHP'
        /**
         * @template TFirst
         * @template TSecond
         */
        class Pair
        {
            /**
             * @return TFirst|TSecond
             */
            public function pick(): mixed
            {
                return null;
            }
        }
        PHP],
]);

it('fails mixed where the docblock does not name a template type', function (string $source, int $line) {
    $file = TestFile::of(Kind::SOURCE, 'src/Recorder.php', "<?php\n\n".$source);

    expect($file->lines(new NoMixedTypes))->toBe([$line]);
})->with([
    'a docblock that only narrows the type' => [<<<'PHP'
        class Recorder
        {
            /**
             * @return string|null
             */
            public function name(): mixed
            {
                return null;
            }
        }
        PHP, 8],
    'a template that holds a list' => [<<<'PHP'
        /**
         * @template TValue
         */
        class Box
        {
            /**
             * @return list<TValue>
             */
            public function all(): mixed
            {
                return [];
            }
        }
        PHP, 11],
    'a template of another method' => [<<<'PHP'
        class Floor
        {
            /**
             * @template TResult
             *
             * @return TResult
             */
            public function hold(): mixed
            {
                return null;
            }

            /**
             * @return TResult
             */
            public function release(): mixed
            {
                return null;
            }
        }
        PHP, 18],
    'a parameter the template docblock does not describe' => [<<<'PHP'
        class Floor
        {
            /**
             * @template TResult
             *
             * @param  TResult  $other
             */
            public function hold(mixed $work, mixed $other): void
            {
                //
            }
        }
        PHP, 10],
    'no docblock' => [<<<'PHP'
        class Recorder
        {
            public function name(): mixed
            {
                return null;
            }
        }
        PHP, 5],
]);

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
