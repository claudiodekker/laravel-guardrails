<?php

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Project;
use ClaudioDekker\LaravelGuardrails\SyntaxError;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;
use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Stmt\Class_;

it('reads the files of its kinds', function (Kind $kind) {
    $file = TestFile::of($kind, 'app/Models/User.php', "one\ntwo");

    expect($file->lines(new EveryLine([Kind::SOURCE, Kind::CONFIG])))->toBe([1, 2]);
})->with([
    'source' => Kind::SOURCE,
    'config' => Kind::CONFIG,
]);

it('leaves the files of other kinds alone, whatever their path', function (Kind $kind) {
    $file = TestFile::of($kind, 'app/Models/User.php', "one\ntwo");

    expect($file->lines(new EveryLine([Kind::SOURCE, Kind::CONFIG])))->toBe([]);
})->with([
    'frontend' => Kind::FRONTEND,
    'lang' => Kind::LANG,
    'migrations' => Kind::MIGRATIONS,
    'routes' => Kind::ROUTES,
    'tests' => Kind::TESTS,
    'text' => Kind::TEXT,
    'views' => Kind::VIEWS,
]);

it('reads every kind unless it says otherwise', function (Kind $kind) {
    $file = TestFile::of($kind, 'routes/web.php', "one\ntwo");

    expect($file->lines(new EveryLine))->toBe([1, 2]);
})->with(Kind::cases());

it('reads the files whose name matches its pattern, at any depth', function (string $path) {
    $file = TestFile::of(Kind::TESTS, $path, "one\ntwo");

    expect($file->lines(new EveryLine([Kind::TESTS], ['*Test.php'])))->toBe([1, 2]);
})->with([
    'tests/UserTest.php',
    'tests/Unit/Models/UserTest.php',
]);

it('reads the files whose name matches any of its patterns', function (string $path, array $lines) {
    $file = TestFile::of(Kind::TEXT, $path, "one\ntwo");

    expect($file->lines(new EveryLine([Kind::TEXT], ['*.vue', '*.ts'])))->toBe($lines);
})->with([
    'the first pattern' => ['resources/js/pages/Welcome.vue', [1, 2]],
    'the second pattern' => ['resources/js/app.ts', [1, 2]],
    'neither pattern' => ['resources/js/app.js', []],
]);

it('leaves the files whose name does not match its pattern alone', function (string $path) {
    $file = TestFile::of(Kind::TESTS, $path, "one\ntwo");

    expect($file->lines(new EveryLine([Kind::TESTS], ['*Test.php'])))->toBe([]);
})->with([
    'tests/TestCase.php',
    'tests/Unit/UserTest.phps',
    'tests/UserTest.php.md',
]);

it('skips the files under a vendor folder at any depth', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, "one\ntwo");

    expect($file->lines(new EveryLine))->toBe([]);
})->with([
    'vendor/acme/package/src/Example.php',
    'public/vendor/package/app.js',
    'lang/vendor/package/en.json',
]);

it('reads the files of a folder whose name only contains vendor', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, "one\ntwo");

    expect($file->lines(new EveryLine))->toBe([1, 2]);
})->with([
    'app/Vendors/Example.php',
    'resources/js/vendor.js',
    'app/MyVendor/Example.php',
]);

it('reads a text file that holds a NUL byte after the first 8000 bytes', function () {
    $file = TestFile::of(Kind::TEXT, 'storage/dump.txt', str_repeat('a', 8000)."\0");

    expect($file->lines(new EveryLine))->toBe([1]);
});

it('brings every violation in when the change moves the file out of a vendor folder, which no check read before', function () {
    $file = TestFile::of(Kind::TEXT, 'docs/notes.md', "one\ntwo")->movedFrom('vendor/acme/package/notes.md');

    expect($file->lines(new EveryLine))->toBe([1, 2]);
});

it('is named after its class', function () {
    expect(EveryLine::name())->toBe('EveryLine');
});

it('names an anonymous check after the class it extends, never after the file that declares it', function () {
    $check = new class extends NoEval {};

    expect($check::name())->toBe('NoEval@anonymous')
        ->and($check::fullName())->toBe(NoEval::class.'@anonymous');
});

it('describes the files it reads by their name and their kinds', function (EveryLine $check, string $scope) {
    expect($check->scope())->toBe($scope);
})->with([
    'every file' => [new EveryLine, 'every file'],
    'PHP of every kind' => [new EveryLine(null, ['*.php']), 'PHP'],
    'PHP of one kind' => [new EveryLine([Kind::CONFIG], ['*.php']), 'PHP in config'],
    'PHP of two kinds' => [new EveryLine([Kind::SOURCE, Kind::MIGRATIONS], ['*.php']), 'PHP in source code and migrations'],
    'PHP of the folders Laravel names' => [new EveryLine([Kind::FRONTEND, Kind::LANG, Kind::ROUTES, Kind::VIEWS], ['*.php']), 'PHP in frontend code, translations, routes and views'],
    'PHP of every kind but one' => [new EveryLine(Kind::except(Kind::TESTS), ['*.php']), 'PHP outside tests'],
    'PHP of every kind but two' => [new EveryLine(Kind::except(Kind::TESTS, Kind::TEXT), ['*.php']), 'PHP outside tests and other files'],
    'a name in one kind' => [new EveryLine([Kind::TESTS], ['*Test.php']), '*Test.php in tests'],
    'two names of every kind' => [new EveryLine(null, ['*.vue', '*.ts']), '*.vue, *.ts'],
]);

it('explains its rule with its description unless it says more', function () {
    expect((new EveryLine)->explanation())->toBe('Every line breaks the rule.');
});

it('tells the folders a file sits in from its name and from folders that only contain the name', function (string $path, bool $in) {
    expect((new File($path, '', Kind::TEXT, Project::nowhere()))->isIn('Concerns'))->toBe($in);
})->with([
    ['app/Models/Concerns/HasRoles.php', true],
    ['Concerns/HasRoles.php', true],
    ['app/Concerns.php', false],
    ['app/MyConcerns/HasRoles.php', false],
    ['app/Models/Concernsy/HasRoles.php', false],
]);

it('reads only the files under the paths given to only(), from the project root', function (EveryLine $check, string $path, bool $read) {
    $file = TestFile::of(Kind::SOURCE, $path, "one\ntwo");

    expect($file->lines($check))->toBe($read ? [1, 2] : []);
})->with([
    'a file under the folder' => [(new EveryLine)->only('packages/shop/src'), 'packages/shop/src/Cart.php', true],
    'a file deeper under the folder' => [(new EveryLine)->only('packages/shop/src'), 'packages/shop/src/Models/Cart.php', true],
    'a file beside the folder' => [(new EveryLine)->only('packages/shop/src'), 'packages/shop/tests/CartTest.php', false],
    'a file whose folder only starts with the path' => [(new EveryLine)->only('packages/shop/src'), 'packages/shop/srcs/Cart.php', false],
    'the file itself' => [(new EveryLine)->only('tests/Pest.php'), 'tests/Pest.php', true],
    'a file under a wildcard' => [(new EveryLine)->only('packages/*/src'), 'packages/blog/src/Post.php', true],
    'a file one folder too deep for a wildcard' => [(new EveryLine)->only('packages/*/src'), 'packages/blog/nested/src/Post.php', false],
    'a file under the second of two paths' => [(new EveryLine)->only('app', 'database'), 'database/seeders/Seed.php', true],
    'a file under neither of two paths' => [(new EveryLine)->only('app', 'database'), 'routes/web.php', false],
    'a file under a path of a second only()' => [(new EveryLine)->only('app')->only('database'), 'database/seeders/Seed.php', true],
    'a path written with a leading dot and a trailing slash' => [(new EveryLine)->only('./app/'), 'app/User.php', true],
]);

it('leaves the files and folders given to allowing() alone', function (EveryLine $check, string $path, bool $read) {
    $file = TestFile::of(Kind::SOURCE, $path, "one\ntwo");

    expect($file->lines($check))->toBe($read ? [1, 2] : []);
})->with([
    'the allowed file' => [(new EveryLine)->allowing(['app/Legacy.php' => 'It predates the rule.']), 'app/Legacy.php', false],
    'a file beside the allowed file' => [(new EveryLine)->allowing(['app/Legacy.php' => 'It predates the rule.']), 'app/User.php', true],
    'a file under the allowed folder' => [(new EveryLine)->allowing(['app/Legacy/' => 'It predates the rule.']), 'app/Legacy/Thing.php', false],
    'a file outside the allowed folder' => [(new EveryLine)->allowing(['app/Legacy' => 'It predates the rule.']), 'app/User.php', true],
    'an allowed file under an only() path' => [(new EveryLine)->only('app')->allowing(['app/Legacy.php' => 'It predates the rule.']), 'app/Legacy.php', false],
    'another file under an only() path' => [(new EveryLine)->only('app')->allowing(['app/Legacy.php' => 'It predates the rule.']), 'app/User.php', true],
]);

it('still reads only the files of its kinds under an only() path', function () {
    $file = TestFile::of(Kind::TESTS, 'packages/shop/tests/CartTest.php', "one\ntwo");

    expect($file->lines((new EveryLine([Kind::SOURCE]))->only('packages')))->toBe([]);
});

it('refuses an only() path that is the project root', function (string $path) {
    expect(fn () => (new EveryLine)->only($path))
        ->toThrow(InvalidArgumentException::class, 'only() takes paths from the project root, such as packages/*/src.');
})->with(['', '.', '/', './']);

it('refuses an allowing() entry without a path or without a reason', function (array $paths) {
    expect(fn () => (new EveryLine)->allowing($paths))
        ->toThrow(InvalidArgumentException::class, "allowing() takes each path with the reason it may break the rule, as ['tests/Feature/LoginTest.php' => 'reason'].");
})->with([
    'a path without a reason' => [['tests/Feature/LoginTest.php']],
    'a blank reason' => [['tests/Feature/LoginTest.php' => ' ']],
]);

it('refuses an allowing() entry for the project root, which would turn the check off', function (string $path) {
    expect(fn () => (new EveryLine)->allowing([$path => 'It predates the rule.']))
        ->toThrow(InvalidArgumentException::class, "allowing() takes paths below the project root, because '{$path}' would turn the check off. Remove the check from guardrails.php instead.");
})->with(['.', './', '/']);

it('describes its coverage as its scope, under the only() paths when it has them', function (EveryLine $check, string $coverage) {
    expect($check->coverage())->toBe($coverage);
})->with([
    'no paths' => [new EveryLine([Kind::SOURCE], ['*.php']), 'PHP in source code'],
    'one path' => [(new EveryLine([Kind::SOURCE], ['*.php']))->only('packages/*/src'), 'PHP in source code, under packages/*/src'],
    'two paths' => [(new EveryLine)->only('app/', 'database'), 'every file, under app, database'],
    'allowed files' => [(new EveryLine)->allowing(['app/Legacy.php' => 'It predates the rule.']), 'every file'],
]);

it('brings in a violation of a node when the change edits any of its lines', function () {
    $rule = new class extends Check
    {
        public function description(): string
        {
            return 'At most two methods per class.';
        }

        public function message(): string
        {
            return 'Split the class.';
        }

        protected function violations(File $file): array
        {
            $large = array_filter($file->findInstanceOf(Class_::class), fn (Class_ $class) => count($class->getMethods()) > 2);

            return array_map(Violation::at(...), array_values($large));
        }
    };

    $file = TestFile::of(Kind::SOURCE, 'app/Foo.php', "<?php\n\nclass Foo\n{\n    public function a() {}\n    public function b() {}\n    public function c() {}\n}\n")
        ->changedFrom("<?php\n\nclass Foo\n{\n    public function a() {}\n    public function b() {}\n}\n");

    expect($file->lines($rule))->toBe([3]);
});

it('brings every violation in when the change moves the file out of an allowed folder', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Dyn.php', "<?php\n\nfunction run(\$code)\n{\n    eval(\$code);\n}\n")->movedFrom('app/Legacy/Dyn.php');

    expect($file->lines((new NoEval)->allowing(['app/Legacy' => 'It predates the rule.'])))->toBe([5]);
});

it('brings every violation in when the change moves the file into a path the check is limited to', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Example.php', "one\ntwo")->movedFrom('legacy/Example.php');

    expect($file->lines((new EveryLine)->only('app')))->toBe([1, 2]);
});

it('brings every violation in when the change moves the file into a kind the check reads', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Example.php', "one\ntwo")->movedFrom('tests/Example.php', Kind::TESTS);

    expect($file->lines(new EveryLine([Kind::SOURCE])))->toBe([1, 2]);
});

it('judges a file the change moves within its coverage by the lines it changed', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Renamed.php', "one\ntwo")->movedFrom('app/Example.php');

    expect($file->lines((new EveryLine)->only('app')))->toBe([]);
});

it('brings every violation in when it cannot tell whether it reads the file before the change, because that file does not parse', function () {
    $rule = new class extends Check
    {
        public function description(): string
        {
            return 'No class runs eval().';
        }

        public function message(): string
        {
            return 'Remove the eval().';
        }

        protected function reads(File $file): bool
        {
            return parent::reads($file) && $file->findInstanceOf(Class_::class) !== [];
        }

        protected function violations(File $file): array
        {
            return array_map(Violation::at(...), $file->findInstanceOf(Eval_::class));
        }
    };

    $file = TestFile::of(Kind::SOURCE, 'app/Dyn.php', "<?php\n\nclass Dyn\n{\n    public function run(\$code)\n    {\n        eval(\$code);\n    }\n}\n")
        ->changedFrom("<?php\n\nclass Dyn\n{\n    public function run(\$code)\n    {\n        eval(\$code);\n    }\n");

    expect($file->lines($rule))->toBe([7]);
});

it('brings a whole-file violation in when the file before the change does not parse, and still judges the others by the lines the change touched', function () {
    $rule = new class extends Check
    {
        public function description(): string
        {
            return 'Every file breaks the rule, and so does every eval().';
        }

        public function message(): string
        {
            return 'Fix it.';
        }

        protected function violations(File $file): array
        {
            return [Violation::file(), ...array_map(Violation::at(...), $file->findInstanceOf(Eval_::class))];
        }

        protected function isNewWholeFile(Violation $violation, File $file, File $previous): bool
        {
            return count($file->statements()) > count($previous->statements());
        }
    };

    $file = TestFile::of(Kind::SOURCE, 'app/Dyn.php', "<?php\n\nclass Dyn\n{\n    public function run(\$code)\n    {\n        eval(\$code);\n    }\n}\n")
        ->changedFrom("<?php\n\nclass Dyn\n{\n    public function run(\$code)\n    {\n        eval(\$code);\n    }\n");

    expect(array_column($file->reports($rule), 'line'))->toBe([null]);
});

it('lets a syntax error of another file than the one it judges through, so the check crashes instead of passing', function () {
    $rule = new class extends Check
    {
        public function description(): string
        {
            return 'Every file breaks the rule.';
        }

        public function message(): string
        {
            return 'Remove the file.';
        }

        protected function violations(File $file): array
        {
            (new File('app/Other.php', "<?php\n\nclass {\n", Kind::SOURCE, $file->project))->statements();

            return [];
        }
    };

    expect(fn () => TestFile::of(Kind::SOURCE, 'app/a.php', "<?php\n")->reports($rule))->toThrow(SyntaxError::class);
});

it('refuses a files() pattern that names a folder before it reads a file, and when a test reads one', function () {
    $rule = new EveryLine(null, ['*.php', 'config/*.php']);
    $message = EveryLine::class.": files() takes patterns on the file's name, such as *.php. config/*.php names a folder: declare the kinds with kinds() and limit the paths with only() in guardrails.php.";

    expect(fn () => $rule->ensurePatterns())->toThrow(InvalidArgumentException::class, $message)
        ->and(fn () => TestFile::of(Kind::CONFIG, 'config/app.php', "<?php\n")->reports($rule))->toThrow(InvalidArgumentException::class, $message);
});

it('has no label but the name as() gives it', function () {
    expect((new EveryLine)->label())->toBeNull()
        ->and((new EveryLine)->as(' NoLines ')->label())->toBe('NoLines');
});

it('refuses an empty name from as()', function (string $name) {
    expect(fn () => (new EveryLine)->as($name))
        ->toThrow(InvalidArgumentException::class, "as() takes the name to print the check's failures under, such as ->as('NoCrmInBilling').");
})->with(['', ' ']);

it('gives no message of its own unless it says one', function () {
    $rule = new class extends Check
    {
        public function description(): string
        {
            return 'Every line breaks the rule.';
        }

        protected function violations(File $file): array
        {
            return [Violation::line(1, 'Remove the line.')];
        }
    };

    expect($rule->message())->toBeNull()
        ->and(TestFile::of(Kind::SOURCE, 'app/a.php', "<?php\n")->reports($rule))->toEqual([['line' => 1, 'message' => 'Remove the line.']]);
});
