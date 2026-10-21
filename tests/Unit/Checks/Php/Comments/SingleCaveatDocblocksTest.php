<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Comments\SingleCaveatDocblocks;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a docblock with more than one paragraph after its summary', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        class Invoices
        {
            /**
             * Count a failed payment, and refuse it once the count is spent.
             *
             * A card that names no customer is counted by its fingerprint.
             *
             * A store failure is reported and refuses the payment.
             *
             * @return bool
             */
            public function count(): bool
            {
                return true;
            }
        }
        PHP);

    expect($file->lines(new SingleCaveatDocblocks))->toBe([10]);
});

it('passes a summary with one caveat paragraph of any length, and tags', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        /**
         * Hold the invoice until the payment has cleared.
         *
         * It waits with the app's clock and sleeps with Sleep, so a faked
         * sleep moves a faked clock by the whole wait, however the lines run.
         *
         * @template TResult
         *
         * @param  Closure(): TResult  $work  The work to run, and a description that wraps
         *                                     onto a second line.
         */
        function hold() {
            //
        }
        PHP);

    expect($file->lines(new SingleCaveatDocblocks))->toBe([]);
});
