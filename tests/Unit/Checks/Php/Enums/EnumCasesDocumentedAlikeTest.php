<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Enums\EnumCasesDocumentedAlike;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails the cases without a docblock in an enum that documents some of its cases', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Enums/Mode.php', <<<'PHP'
        <?php

        enum Mode
        {
            /**
             * The first mode.
             */
            case ALPHA;

            case BETA;

            /**
             * The third mode.
             */
            case GAMMA;

            case DELTA;
        }
        PHP);

    expect($file->lines(new EnumCasesDocumentedAlike))->toBe([10, 17]);
});

it('passes an enum that documents every case', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Enums/Mode.php', <<<'PHP'
        <?php

        enum Mode
        {
            /**
             * The first mode.
             */
            case ALPHA;

            /**
             * The second mode.
             */
            case BETA;
        }
        PHP);

    expect($file->lines(new EnumCasesDocumentedAlike))->toBe([]);
});

it('passes an enum that documents none of its cases', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Enums/Mode.php', <<<'PHP'
        <?php

        enum Mode
        {
            case ALPHA;
            case BETA;
        }
        PHP);

    expect($file->lines(new EnumCasesDocumentedAlike))->toBe([]);
});

it('counts the cases of each enum on their own', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Enums/Modes.php', <<<'PHP'
        <?php

        enum Mode
        {
            /**
             * The first mode.
             */
            case ALPHA;
        }

        enum Level
        {
            case LOW;
        }
        PHP);

    expect($file->lines(new EnumCasesDocumentedAlike))->toBe([]);
});
