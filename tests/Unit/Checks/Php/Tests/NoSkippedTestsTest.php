<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\NoSkippedTests;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a PHPUnit test that skips itself or marks itself incomplete', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', <<<'PHP'
        <?php

        class OrderTest extends TestCase
        {
            public function test_it_places_an_order(): void
            {
                $this->markTestSkipped('Flaky.');
            }

            public function test_it_cancels_an_order(): void
            {
                static::markTestIncomplete();
            }

            public function test_it_refunds_an_order(): void
            {
                self::markTestSkipped();
            }
        }
        PHP);

    expect($file->lines(new NoSkippedTests))->toBe([7, 12, 17]);
});

it('fails a Pest test that is skipped or a todo', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', <<<'PHP'
        <?php

        it('places an order', function () {
            expect(1)->toBe(1);
        })->skip();

        test('cancels an order')->todo();

        it('refunds an order', fn () => expect(1)->toBe(1))->with([1, 2])->skip('Not yet.');

        todo('ships an order');

        describe('invoices', function () {
            it('sends an invoice', fn () => expect(1)->toBe(1));
        })->skip(PHP_OS_FAMILY === 'Windows');
        PHP);

    expect($file->lines(new NoSkippedTests))->toBe([5, 7, 9, 11, 15]);
});

it('passes a skip() that is not chained onto a Pest test', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', <<<'PHP'
        <?php

        it('pages the orders', function () {
            $second = collect([1, 2, 3])->skip(1);
            $rest = Order::query()->skip(10)->take(5)->get();

            expect($second->count())->toBe(2);
        });
        PHP);

    expect($file->lines(new NoSkippedTests))->toBe([]);
});

it('fails a skip in a base test case or the Pest helper file', function (string $path) {
    $file = TestFile::of(Kind::TESTS, $path, "<?php\n\nbeforeEach(fn () => \$this->markTestSkipped());\n");

    expect($file->lines(new NoSkippedTests))->toBe([3]);
})->with(['tests/Pest.php', 'tests/TestCase.php']);

it('fails only the skip the change adds', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', "<?php\n\nit('places an order')->todo();\nit('cancels an order')->skip();\n")
        ->changedFrom("<?php\n\nit('places an order')->todo();\nit('cancels an order');\n");

    expect($file->lines(new NoSkippedTests))->toBe([4]);
});
