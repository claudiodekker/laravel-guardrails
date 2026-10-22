<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Comments\ClassDocblocksHoldOnlyTags;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a class docblock that holds prose', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        /**
         * Hold the invoices of a customer.
         */
        class Invoices {}
        PHP);

    expect($file->lines(new ClassDocblocksHoldOnlyTags))->toBe([3]);
});

it('fails prose beside tags, above or below them', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', <<<'PHP'
        <?php

        /**
         * @extends Factory<User>
         *
         * Builds users for the tests.
         */
        class UserFactory {}

        /**
         * A user of the app.
         *
         * @mixin Builder
         */
        class User {}
        PHP);

    expect($file->lines(new ClassDocblocksHoldOnlyTags))->toBe([3, 10]);
});

it('fails an interface, trait and enum docblock that holds prose', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/Types.php', <<<'PHP'
        <?php

        /** Something that sends. */
        interface Sends {}

        /** Something that counts. */
        trait Counts {}

        /** A status. */
        enum Status {}
        PHP);

    expect($file->lines(new ClassDocblocksHoldOnlyTags))->toBe([3, 6, 9]);
});

it('fails the docblock above the attributes of a class', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', <<<'PHP'
        <?php

        /**
         * A user of the app.
         */
        #[Table('users')]
        class User {}
        PHP);

    expect($file->lines(new ClassDocblocksHoldOnlyTags))->toBe([3]);
});

it('passes a class docblock that holds only tags', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', <<<'PHP'
        <?php

        /**
         * @extends Factory<User>
         * @property-read string $name
         * @mixin Builder
         *
         * @phpstan-consistent-constructor
         * @phpstan-type Row array{id: int}
         */
        #[Table('users')]
        class User {}

        /** @template TModel */
        interface Repository {}

        /** @mixin Builder */
        enum Status {}
        PHP);

    expect($file->lines(new ClassDocblocksHoldOnlyTags))->toBe([]);
});

it('passes a tag whose description wraps onto indented lines', function () {
    $file = TestFile::of(Kind::SOURCE, 'tests/Support/BuildManifest.php', <<<'PHP'
        <?php

        /**
         * @phpstan-type Manifest array{
         *     name: string,
         *     files: list<string>,
         * }
         * @property string $name  The name of the build, and a description that
         *                         wraps onto a second line.
         */
        class BuildManifest {}
        PHP);

    expect($file->lines(new ClassDocblocksHoldOnlyTags))->toBe([]);
});

it('fails prose on the line right under a tag', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', <<<'PHP'
        <?php

        /**
         * @mixin Builder
         * Builds users.
         */
        class User {}
        PHP);

    expect($file->lines(new ClassDocblocksHoldOnlyTags))->toBe([3]);
});

it('ignores a class without a docblock, a method docblock, a closure and an anonymous class', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', <<<'PHP'
        <?php

        class Invoices
        {
            /**
             * Count the invoices.
             */
            public function count(): int
            {
                return 0;
            }
        }

        /** Not a class. */
        $made = new class {};
        PHP);

    expect($file->lines(new ClassDocblocksHoldOnlyTags))->toBe([]);
});

it('reads every kind of code and the test support code, but not a test case', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\n/** A thing. */\nclass Thing {}\n");

    expect($file->lines(new ClassDocblocksHoldOnlyTags))->toBe($lines);
})->with([
    'source code' => ['app/Support/Thing.php', Kind::SOURCE, [3]],
    'test support code' => ['tests/Support/Thing.php', Kind::TESTS, [3]],
    'a test case' => ['tests/Feature/InvoiceTest.php', Kind::TESTS, []],
    'a class named like a test in the source code' => ['app/Models/TensileTest.php', Kind::SOURCE, [3]],
]);

it('says it reads every PHP file except the test cases', function () {
    expect((new ClassDocblocksHoldOnlyTags)->scope())->toBe('PHP except *Test.php files in the tests');
});

it('lets a class keep its prose when the check allows the file', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Invoices.php', "<?php\n\n/**\n * Keep this, because the vendor reads it.\n */\nclass Invoices {}\n");

    expect($file->lines((new ClassDocblocksHoldOnlyTags)->allowing(['app/Services/Invoices.php' => 'The vendor reads it.'])))->toBe([]);
});
