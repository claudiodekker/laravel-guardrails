<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Comments\NoAnalyserIgnores;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails every form of a PHPStan ignore and a Psalm suppression, in a line comment or a docblock', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Report.php', <<<'PHP'
        <?php

        $a = one(); // @phpstan-ignore argument.type
        $b = two(); // @phpstan-ignore
        $c = three(); // @phpstan-ignore-line
        // @phpstan-ignore-next-line
        $d = four();
        /** @psalm-suppress MixedAssignment */
        $e = five();
        /**
         * @param  int  $x
         * @phpstan-ignore return.type
         */
        # @psalm-suppress InvalidReturnType
        /* @phpstan-ignore-line */
        PHP);

    expect($file->lines(new NoAnalyserIgnores))->toBe([3, 4, 5, 6, 8, 12, 14, 15]);
});

it('passes other analyser tags, plain comments and strings', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Report.php', <<<'PHP'
        <?php

        /** @phpstan-var int $a */
        /** @phpstan-assert string $b */
        /** @psalm-return list<int> */
        // Do not ignore the analyser.
        $c = '@phpstan-ignore-line';
        $d = "// @psalm-suppress MixedAssignment";
        PHP);

    expect($file->lines(new NoAnalyserIgnores))->toBe([]);
});

it('fails the ignore the change adds, and not the one it leaves alone', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Report.php', "<?php\n\n\$a = one(); // @phpstan-ignore argument.type\n\$b = two(); // @psalm-suppress MixedAssignment\n")
        ->changedFrom("<?php\n\n\$a = one(); // @phpstan-ignore argument.type\n\$b = two();\n");

    expect($file->lines(new NoAnalyserIgnores))->toBe([4]);
});

it('fails an ignore in every kind of PHP file', function (Kind $kind) {
    $file = TestFile::of($kind, 'somewhere/Thing.php', "<?php\n\n\$a = one(); // @phpstan-ignore argument.type\n");

    expect($file->lines(new NoAnalyserIgnores))->toBe([3]);
})->with(Kind::cases());

it('names the fix in its report', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Report.php', "<?php\n\n\$a = one(); // @phpstan-ignore argument.type\n");

    expect($file->reports(new NoAnalyserIgnores))->toBe([
        ['line' => 3, 'message' => 'Fix the error the analyser reports instead of silencing it, by correcting the type or the code it points at.'],
    ]);
});
