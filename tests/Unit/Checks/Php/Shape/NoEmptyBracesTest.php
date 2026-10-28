<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\NoEmptyBraces;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails the empty body of a constructor, a method, a function and a class', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Http/Middleware/RefuseCrossSiteRequests.php', <<<'PHP'
        <?php

        class RefuseCrossSiteRequests
        {
            public function __construct(
                protected Encrypter $encrypter,
            ) {}

            public function terminate(): void { }
        }

        function helper()
        {
        }

        class Marker {}
        PHP);

    expect($file->lines(new NoEmptyBraces))->toBe([7, 9, 13, 16]);
});

it('fails the empty body of a control structure', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        try {
            send();
        } catch (Exception) {}

        foreach ($invoices as $invoice) {
        }
        PHP);

    expect($file->lines(new NoEmptyBraces))->toBe([5, 7]);
});

it('reports an empty body at its opening brace, naming the fix', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/helpers.php', "<?php\n\nfunction helper()\n{\n}\n");

    expect($file->reports(new NoEmptyBraces))->toEqual([
        ['line' => 4, 'message' => 'Write the empty body over three lines: the {, a line holding only //, and the }.'],
    ]);
});

it('passes an empty body that holds a lone // or another comment', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Http/Middleware/RefuseCrossSiteRequests.php', <<<'PHP'
        <?php

        class RefuseCrossSiteRequests
        {
            public function __construct(
                protected Encrypter $encrypter,
            ) {
                //
            }

            public function terminate(): void
            {
                // The response is already sent.
            }
        }
        PHP);

    expect($file->lines(new NoEmptyBraces))->toBe([]);
});

it('passes the empty braces of a closure and an anonymous class, and braces in a string', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Providers/AppServiceProvider.php', <<<'PHP'
        <?php

        $this->app->terminating(function () {});
        $migration = new class extends Migration {};
        $greeting = "Hello {$name} {}";
        PHP);

    expect($file->lines(new NoEmptyBraces))->toBe([]);
});
