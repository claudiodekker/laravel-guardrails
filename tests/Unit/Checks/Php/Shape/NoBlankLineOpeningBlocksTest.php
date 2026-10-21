<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\NoBlankLineOpeningBlocks;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a block that opens with a blank line', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        class Invoices
        {

            public function total(): int
            {

                return 1;
            }
        }
        PHP);

    expect($file->lines(new NoBlankLineOpeningBlocks))->toBe([5, 8]);
});

it('fails a block that opens with a blank line before a comment', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        if ($ready) {

            // Send the invoice.
            send();
        }
        PHP);

    expect($file->lines(new NoBlankLineOpeningBlocks))->toBe([4]);
});

it('passes a block that opens with its first statement, and braces that open no block', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        class Invoices
        {
            public function greet(string $name): string
            {
                $greeting = "Hello {$name}";

                return $greeting;
            }
        }
        PHP);

    expect($file->lines(new NoBlankLineOpeningBlocks))->toBe([]);
});
