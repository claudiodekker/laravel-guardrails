<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\NoRemovedAssertions;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

$message = 'Restore the assertions this test lost, or assert the same behavior another way, because a test with fewer assertions checks less.';

it('fails a PHPUnit test that keeps its name and has fewer assertions, at the name of the method', function () use ($message) {
    $before = <<<'PHP'
        <?php

        namespace Tests\Unit;

        use PHPUnit\Framework\Attributes\Test;

        class OrderTest extends TestCase
        {
            public function test_it_places_an_order(): void
            {
                $order = place();
                $this->assertSame(1, $order->id);
                $this->assertTrue($order->placed);
            }

            #[Test]
            public function it_cancels_an_order(): void
            {
                self::assertCount(2, cancel());
                static::assertNull(cancel());
            }

            /** @test */
            public function it_refunds_an_order(): void
            {
                $this->assertTrue(refund());
            }

            public function test_it_ships_an_order(): void
            {
                $this->assertTrue(ship());
            }
        }
        PHP;

    $after = <<<'PHP'
        <?php

        namespace Tests\Unit;

        use PHPUnit\Framework\Attributes\Test;

        class OrderTest extends TestCase
        {
            public function test_it_places_an_order(): void
            {
                $order = place();
                $this->assertSame(1, $order->id);
            }

            #[Test]
            public function it_cancels_an_order(): void
            {
                self::assertCount(2, cancel());
            }

            /** @test */
            public function it_refunds_an_order(): void
            {
                refund();
            }

            public function test_it_ships_an_order(): void
            {
                $this->assertTrue(ship());
                $this->assertTrue(ship());
            }
        }
        PHP;

    $file = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', $after)->changedFrom($before);

    expect($file->reports(new NoRemovedAssertions))->toBe([
        ['line' => 9, 'message' => $message],
        ['line' => 16, 'message' => $message],
        ['line' => 22, 'message' => $message],
    ]);
});

it('fails a Pest test that has fewer expectations, assertions or chained and() calls', function () use ($message) {
    $before = <<<'PHP'
        <?php

        it('places an order', function () {
            expect(place())->toBeTrue();
            expect(count())->toBe(1);
        });

        test('cancels an order', function () {
            expect(cancel())->toBeTrue()->and(count())->toBe(0);
        });

        it('refunds an order', function () {
            $this->assertTrue(refund());
        });

        it('lists the orders')->get('/orders')->assertOk()->assertSee('Order');
        PHP;

    $after = <<<'PHP'
        <?php

        it('places an order', function () {
            expect(place())->toBeTrue();
        });

        test('cancels an order', function () {
            expect(cancel())->toBeTrue();
        });

        it('refunds an order', function () {
            refund();
        });

        it('lists the orders')->get('/orders')->assertOk();
        PHP;

    $file = TestFile::of(Kind::TESTS, 'tests/Feature/OrderTest.php', $after)->changedFrom($before);

    expect($file->reports(new NoRemovedAssertions))->toBe([
        ['line' => 3, 'message' => $message],
        ['line' => 7, 'message' => $message],
        ['line' => 11, 'message' => $message],
        ['line' => 15, 'message' => $message],
    ]);
});

it('passes a test that keeps its assertions or has more', function () {
    $before = "<?php\n\nit('places an order', function () {\n    expect(place())->toBeTrue();\n    expect(count())->toBe(1);\n});\n\nclass OrderTest extends TestCase\n{\n    public function test_it_places_an_order(): void\n    {\n        \$this->assertTrue(place());\n    }\n}\n";
    $after = "<?php\n\nit('places an order', function () {\n    expect(place())->toBeTrue();\n    expect(count())->toBe(1);\n    expect(total())->toBe(5);\n});\n\nclass OrderTest extends TestCase\n{\n    public function test_it_places_an_order(): void\n    {\n        \$this->assertTrue(place());\n        \$this->assertSame(1, count());\n    }\n}\n";

    $more = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', $after)->changedFrom($before);
    $same = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', str_replace('place()', 'order()', $before))->changedFrom($before);

    expect($more->reports(new NoRemovedAssertions))->toBe([])
        ->and($same->reports(new NoRemovedAssertions))->toBe([]);
});

it('passes a test that is deleted or renamed, because its behavior may be gone', function () {
    $before = "<?php\n\nclass OrderTest extends TestCase\n{\n    public function test_it_places_an_order(): void\n    {\n        \$this->assertTrue(place());\n    }\n\n    public function test_it_cancels_an_order(): void\n    {\n        \$this->assertTrue(cancel());\n    }\n}\n\nit('refunds an order', function () {\n    expect(refund())->toBeTrue();\n});\n\ntest('ships an order', function () {\n    expect(ship())->toBeTrue();\n});\n";
    $after = "<?php\n\nclass OrderTest extends TestCase\n{\n    public function test_it_places_orders(): void\n    {\n        \$this->assertTrue(place());\n    }\n}\n\nit('refunds a placed order', function () {\n    refund();\n});\n";

    $file = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', $after)->changedFrom($before);

    expect($file->reports(new NoRemovedAssertions))->toBe([]);
});

it('tells apart Pest tests that share a description, by the describe blocks and the function around them', function () use ($message) {
    $before = <<<'PHP'
        <?php

        describe('orders', function () {
            it('works', function () {
                expect(place())->toBeTrue();
                expect(count())->toBe(1);
            });
        });

        describe('invoices', function () {
            it('works', function () {
                expect(invoice())->toBeTrue();
            });
        });

        it('works', function () {
            expect(1)->toBe(1);
            expect(2)->toBe(2);
        });

        test('works', function () {
            expect(1)->toBe(1);
        });
        PHP;

    $after = str_replace("        expect(count())->toBe(1);\n", '', $before);
    $after = str_replace("    expect(2)->toBe(2);\n", '', $after);

    $file = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', $after)->changedFrom($before);

    expect($file->reports(new NoRemovedAssertions))->toBe([
        ['line' => 4, 'message' => $message],
        ['line' => 15, 'message' => $message],
    ]);
});

it('keys a test by its class in the namespace, so the same method name in another class is another test', function () use ($message) {
    $before = "<?php\n\nnamespace Tests\\Unit;\n\nclass OrderTest extends TestCase\n{\n    public function test_it_works(): void\n    {\n        \$this->assertTrue(order());\n    }\n}\n\nclass InvoiceTest extends TestCase\n{\n    public function test_it_works(): void\n    {\n        \$this->assertTrue(invoice());\n    }\n}\n";
    $after = str_replace("        \$this->assertTrue(order());\n", "        order();\n", $before);

    $file = TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', $after)->changedFrom($before);

    expect($file->reports(new NoRemovedAssertions))->toBe([['line' => 7, 'message' => $message]]);
});

it('passes a method that is not a test, a file the change adds and a file the change deletes', function () {
    $before = "<?php\n\nclass OrderTest extends TestCase\n{\n    protected function assertPlaced(): void\n    {\n        \$this->assertTrue(place());\n    }\n}\n";
    $after = "<?php\n\nclass OrderTest extends TestCase\n{\n    protected function assertPlaced(): void\n    {\n        place();\n    }\n}\n";
    $stripped = "<?php\n\nit('places an order', function () {\n    place();\n});\n";

    expect(TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', $after)->changedFrom($before)->reports(new NoRemovedAssertions))->toBe([])
        ->and(TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', $stripped)->reports(new NoRemovedAssertions))->toBe([])
        ->and(TestFile::of(Kind::TESTS, 'tests/Unit/OrderTest.php', $stripped)->deleted()->reports(new NoRemovedAssertions))->toBe([]);
});

it('compares a test file with the file it was moved from', function () use ($message) {
    $before = "<?php\n\nit('places an order', function () {\n    expect(place())->toBeTrue();\n});\n";
    $after = "<?php\n\nit('places an order', function () {\n    place();\n});\n";

    $moved = TestFile::of(Kind::TESTS, 'tests/Feature/OrderTest.php', $before)->movedFrom('tests/Unit/OrderTest.php');
    $movedAndStripped = TestFile::of(Kind::TESTS, 'tests/Feature/OrderTest.php', $after)->movedFrom('tests/Unit/OrderTest.php', Kind::TESTS)->changedFrom($before);

    expect($moved->reports(new NoRemovedAssertions))->toBe([])
        ->and($movedAndStripped->reports(new NoRemovedAssertions))->toBe([['line' => 3, 'message' => $message]]);
});

it('reads only the test files of the tests', function (string $path, Kind $kind, bool $reads) {
    $file = TestFile::of($kind, $path, "<?php\n\nit('places an order', function () {\n    place();\n});\n")
        ->changedFrom("<?php\n\nit('places an order', function () {\n    expect(place())->toBeTrue();\n});\n");

    expect($file->lines(new NoRemovedAssertions))->toBe($reads ? [3] : []);
})->with([
    'a feature test' => ['tests/Feature/OrderTest.php', Kind::TESTS, true],
    'a nested unit test' => ['tests/Unit/Models/OrderTest.php', Kind::TESTS, true],
    'the Pest helper file' => ['tests/Pest.php', Kind::TESTS, false],
    'a test of the source code' => ['app/OrderTest.php', Kind::SOURCE, false],
]);
