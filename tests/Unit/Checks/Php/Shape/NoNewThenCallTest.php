<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\NoNewThenCall;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a new object with several arguments called at once with several arguments', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        (new Invoice($order, $customer))->send($mailer, $copy);
        new Invoice($order, $customer)->send($mailer, $copy);
        (new Invoice($order, $customer))?->send($mailer, $copy);
        PHP);

    expect($file->lines(new NoNewThenCall))->toBe([3, 4, 5]);
});

it('passes when the constructor or the call takes one argument or none', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        (new Invoice($order))->send($mailer, $copy);
        (new Invoice($order, $customer))->send($mailer);
        (new Invoice)->send();
        (new Invoice($order, $customer))->send();
        PHP);

    expect($file->lines(new NoNewThenCall))->toBe([]);
});

it('passes a new object that is named first', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        $invoice = new Invoice($order, $customer);

        $invoice->send($mailer, $copy);
        PHP);

    expect($file->lines(new NoNewThenCall))->toBe([]);
});

it('reads every PHP file', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, "<?php\n\n(new Invoice(\$order, \$customer))->send(\$mailer, \$copy);\n");

    expect($file->lines(new NoNewThenCall))->toBe([3]);
})->with([
    'application code' => 'app/Services/Invoices.php',
    'a test' => 'tests/Unit/InvoiceTest.php',
]);

it('fails a call on a new object once the change gives the constructor a second argument on a line of its own', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', "<?php\n\n(new Invoice(\n    \$order,\n    \$customer,\n))->send(\$mailer, \$copy);\n")
        ->changedFrom("<?php\n\n(new Invoice(\n    \$order,\n))->send(\$mailer, \$copy);\n");

    expect($file->lines(new NoNewThenCall))->toBe([6]);
});
