<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Comments\PhpstanIgnoresNameIdentifier;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails an ignore without an identifier in a line comment or a docblock', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Report.php', <<<'PHP'
        <?php

        // @phpstan-ignore
        $a = one(); // @phpstan-ignore-line
        // @phpstan-ignore-next-line
        $b = two();
        $c = three(); // @phpstan-ignore argument.type
        $d = four(); // @phpstan-ignore argument.type, return.type (until the next release)
        /** @phpstan-ignore */
        $e = five();
        /** @phpstan-ignore-next-line */
        $f = six();
        /**
         * @phpstan-ignore method.notFound
         * @phpstan-ignore
         */
        $g = seven();
        # @phpstan-ignore-line
        $h = '@phpstan-ignore-line';
        /* @phpstan-ignore-line */
        PHP);

    expect($file->lines(new PhpstanIgnoresNameIdentifier))->toBe([3, 4, 5, 9, 11, 15, 18, 20]);
});

it('passes other analyser tags and plain comments', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Report.php', <<<'PHP'
        <?php

        /** @phpstan-ignore-next-line argument.type */
        /** @phpstan-var int $a */
        /** @phpstan-assert string $b */
        // Ignore this line in the docs.
        // @psalm-suppress MixedAssignment
        PHP);

    expect($file->lines(new PhpstanIgnoresNameIdentifier))->toBe([3]);
});
