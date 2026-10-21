<?php

use ClaudioDekker\LaravelGuardrails\Change;
use ClaudioDekker\LaravelGuardrails\Checks\Php\NoSyntaxErrors;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Project;
use ClaudioDekker\LaravelGuardrails\SyntaxError;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;
use ClaudioDekker\LaravelGuardrails\Violation;

it('fails a PHP file that does not parse, as a whole, shown at the line of the error', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Broken.php', "<?php\n\nclass Broken\n{\n    public function () {}\n}\n");

    expect($file->reports(new NoSyntaxErrors))->toEqual([
        ['line' => 5, 'message' => "Syntax error, unexpected '('. Fix it so the file parses, then run the checks again."],
    ]);
});

it('fails a file that does not parse whatever lines the change touched', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Broken.php', "<?php\n\nclass Broken\n{\n    public function () {}\n}\n\n\$a = 1;\n")
        ->changedFrom("<?php\n\nclass Broken\n{\n    public function () {}\n}\n");

    expect($file->lines(new NoSyntaxErrors))->toBe([5]);
});

it('fails a file that imports one name twice, at the line of the second import', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Dup.php', "<?php\n\nnamespace App;\n\nuse A\\B;\nuse C\\B;\n\nclass Dup {}\n");

    expect($file->reports(new NoSyntaxErrors))->toEqual([
        ['line' => 6, 'message' => 'Cannot use C\\B as B because the name is already in use. Fix it so the file parses, then run the checks again.'],
    ]);
});

it('shows a syntax error the parser places at no line without a line', function () {
    $file = new class('app/Broken.php', "<?php\n", Kind::SOURCE, Project::nowhere()) extends File
    {
        public function statements(): array
        {
            throw new SyntaxError('Syntax error. Fix it so the file parses, then run the checks again.', -1, $this);
        }
    };

    expect((new NoSyntaxErrors)->check(Change::added($file)))->toEqual([Violation::file('Syntax error. Fix it so the file parses, then run the checks again.')]);
});

it('passes a file that parses', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Fine.php', "<?php\n\nclass Fine {}\n");

    expect($file->reports(new NoSyntaxErrors))->toBe([]);
});

it('reads every PHP file of every kind, and no Blade template or other file', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\nclass Broken\n{\n    public function () {}\n}\n");

    expect($file->lines(new NoSyntaxErrors))->toBe($lines);
})->with([
    'source code' => ['app/Broken.php', Kind::SOURCE, [5]],
    'a test' => ['tests/Unit/BrokenTest.php', Kind::TESTS, [5]],
    'a config file' => ['config/broken.php', Kind::CONFIG, [5]],
    'a script' => ['scripts/broken.php', Kind::TEXT, [5]],
    'a Blade template' => ['resources/views/broken.blade.php', Kind::VIEWS, []],
    'a Markdown file' => ['docs/broken.md', Kind::TEXT, []],
]);

it('describes the files it reads as PHP', function () {
    expect((new NoSyntaxErrors)->scope())->toBe('PHP');
});

it('refuses only(), because it runs over every PHP file', function () {
    expect(fn () => (new NoSyntaxErrors)->only('tests'))
        ->toThrow(InvalidArgumentException::class, 'NoSyntaxErrors runs over every PHP file, so it takes no only(). Allow the files that are broken on purpose with allowing() instead.');
});

it('does not parse a stub template', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, "<?php\n\nnamespace {{ namespace }};\n\nclass {NAME}\n{\n}\n");

    expect($file->reports(new NoSyntaxErrors))->toBe([]);
})->with([
    'stubs/model.stub.php',
    'src/stubs/nested/model.stub.php',
]);
