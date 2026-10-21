<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Enums\SortedEnumCases;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails an enum case that sorts before the case above it', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Enums/Mode.php', <<<'PHP'
        <?php

        enum Mode: string
        {
            case BETA = 'b';
            case ALPHA = 'a';
            case GAMMA = 'g';
            case DELTA = 'd';
        }
        PHP);

    expect($file->lines(new SortedEnumCases))->toBe([6, 8]);
});

it('passes enum cases in alphabetical order', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Enums/Mode.php', <<<'PHP'
        <?php

        enum Mode: string
        {
            case ALPHA = 'z';
            case BETA = 'y';
            case DELTA = 'x';
            case GAMMA = 'w';
        }
        PHP);

    expect($file->lines(new SortedEnumCases))->toBe([]);
});

it('passes a file without an enum', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\nclass User {}\n");

    expect($file->lines(new SortedEnumCases))->toBe([]);
});

it('fails a case the change renames so that the case below it sorts first, at the case below', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Enums/Mode.php', "<?php\n\nenum Mode: string\n{\n    case GAMMA = 'a';\n    case BETA = 'b';\n}\n")
        ->changedFrom("<?php\n\nenum Mode: string\n{\n    case ALPHA = 'a';\n    case BETA = 'b';\n}\n");

    expect($file->lines(new SortedEnumCases))->toBe([6]);
});
