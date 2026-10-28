<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Package\ClassesTaggedApiOrInternal;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a class, interface, trait or enum tagged neither @api nor @internal', function () {
    $file = TestFile::of(Kind::SOURCE, 'src/Http/Middleware/RefuseCrossSiteRequests.php', <<<'PHP'
        <?php

        class RefuseCrossSiteRequests
        {
            //
        }

        /**
         * @mixin Builder
         */
        interface Sends
        {
            //
        }

        /**
         * @apiVersion 2
         */
        trait Retries
        {
            //
        }

        /**
         * Says @api in prose.
         */
        enum Status
        {
            //
        }
        PHP);

    expect($file->lines(new ClassesTaggedApiOrInternal))->toBe([3, 11, 19, 27]);
});

it('passes a class tagged @api or @internal, beside other tags', function () {
    $file = TestFile::of(Kind::SOURCE, 'src/Recorder.php', <<<'PHP'
        <?php

        /**
         * @api
         */
        class Recorder
        {
            //
        }

        $listener = new class {
            //
        };

        /**
         * @internal
         *
         * @mixin Builder
         */
        trait Records
        {
            //
        }

        /** @api */
        interface Listens
        {
            //
        }
        PHP);

    expect($file->lines(new ClassesTaggedApiOrInternal))->toBe([]);
});

it('names the fix', function () {
    $file = TestFile::of(Kind::SOURCE, 'src/Recorder.php', "<?php\n\nclass Recorder\n{\n    //\n}\n");

    expect($file->reports(new ClassesTaggedApiOrInternal))->toEqual([
        ['line' => 3, 'message' => 'Tag the class @api when other code may build on it, or @internal when it may change without notice.'],
    ]);
});

it('covers src by default, or the folders it is given, and never the tests', function (string $path, Kind $kind, array $folders, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\nclass Invoice\n{\n    //\n}\n");

    expect($file->lines(new ClassesTaggedApiOrInternal(...$folders)))->toBe($lines);
})->with([
    'a class in src' => ['src/Invoice.php', Kind::SOURCE, [], [3]],
    'a class outside src' => ['app/Billing/Invoice.php', Kind::SOURCE, [], []],
    'a class in the folder an app gives' => ['app/Billing/Invoice.php', Kind::SOURCE, [['app/Billing']], [3]],
    'a class beside the folder an app gives' => ['app/Models/Invoice.php', Kind::SOURCE, [['app/Billing']], []],
    'a class in a package of a monorepo' => ['packages/keystone/src/Invoice.php', Kind::SOURCE, [['packages/*/src']], [3]],
    'a test in src' => ['src/InvoiceTest.php', Kind::TESTS, [], []],
]);

it('says which folders it covers', function () {
    expect((new ClassesTaggedApiOrInternal)->scope())->toBe('PHP outside tests under src')
        ->and((new ClassesTaggedApiOrInternal(['./app/Billing/', 'packages/*/src']))->scope())->toBe('PHP outside tests under app/Billing, packages/*/src');
});

it('refuses no folders, or the project root, naming the form it takes', function (array $folders) {
    new ClassesTaggedApiOrInternal(...$folders);
})->with([
    'no folders' => [[[]]],
    'the project root' => [[['.']]],
])->throws(InvalidArgumentException::class, "ClassesTaggedApiOrInternal takes the folders it covers below the project root, as new ClassesTaggedApiOrInternal(['src']) or new ClassesTaggedApiOrInternal(['packages/*/src']).");
