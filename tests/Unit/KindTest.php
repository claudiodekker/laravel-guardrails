<?php

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;
use ClaudioDekker\LaravelGuardrails\Violation;

it('lets text cover itself and every kind that is not one of the separate kinds', function () {
    $separate = [Kind::CONFIG, Kind::FRONTEND, Kind::LANG, Kind::MIGRATIONS, Kind::ROUTES, Kind::SOURCE, Kind::TESTS, Kind::VIEWS];

    foreach (Kind::cases() as $kind) {
        expect(Kind::TEXT->includes($kind))->toBe(! in_array($kind, $separate, true));
    }
});

it('lets every other kind cover only itself', function (Kind $kind) {
    foreach (Kind::cases() as $other) {
        expect($kind->includes($other))->toBe($kind === $other);
    }
})->with(array_filter(Kind::cases(), fn (Kind $kind) => $kind !== Kind::TEXT));

it('reads a file of a kind a listed kind covers, and no other', function () {
    $check = new class extends Check
    {
        public function description(): string
        {
            return 'Every file breaks the rule.';
        }

        public function message(): string
        {
            return 'Fix it.';
        }

        public function kinds(): array
        {
            return [Kind::TEXT];
        }

        protected function files(): array
        {
            return ['*'];
        }

        protected function violations(File $file): array
        {
            return [Violation::file()];
        }
    };

    expect(TestFile::of(Kind::TEXT, 'README.md', "Docs\n")->lines($check))->toBe([])
        ->and(TestFile::of(Kind::TEXT, 'README.md', "Docs\n")->reports($check))->toBe([['line' => null, 'message' => 'Fix it.']])
        ->and(TestFile::of(Kind::SOURCE, 'app/User.php', "<?php\n")->reports($check))->toBe([]);
});

it('covers a kind added later in text, unless it is added to the separate kinds', function () {
    $separate = (new ReflectionClassConstant(Kind::class, 'SEPARATE'))->getValue();

    expect($separate)->not->toContain(Kind::TEXT);

    foreach (Kind::cases() as $kind) {
        expect(Kind::TEXT->includes($kind))->toBe($kind === Kind::TEXT || ! in_array($kind, $separate, true));
    }
});
