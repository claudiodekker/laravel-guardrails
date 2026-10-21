<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\NoTestCodeInApp;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\TestsInTestSuites;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\TestsInUnitOrFeature;
use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;
use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;
use ClaudioDekker\LaravelGuardrails\Tests\Support\Silent;

it('reports every violation of a file the change adds', function () {
    $file = TestFile::of(Kind::TEXT, 'README.md', "Fast \u{2014} and loose.\nClean.\nSlow \u{2014} and tight.\n");

    $message = 'Replace the em dash with a comma, a colon, parentheses or a new sentence.';

    expect($file->reports(new NoEmDashes))->toBe([['line' => 1, 'message' => $message], ['line' => 3, 'message' => $message]])
        ->and($file->lines(new NoEmDashes))->toBe([1, 3]);
});

it('reports only the violations on the lines the change adds or changes, as git diffs them', function () {
    $file = TestFile::of(Kind::TEXT, 'README.md', "Fast \u{2014} and loose.\nClean.\nSlow \u{2014} and tight.\nNew \u{2014} line.\n")
        ->changedFrom("Fast \u{2014} and loose.\nClean.\nOld \u{2014} line.\n");

    expect(array_column($file->reports(new NoEmDashes), 'line'))->toBe([3, 4]);
});

it('reports a debug call appended to a chain at the line the change adds', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/a.php', "<?php\n\nreturn \$q\n    ->where(1)\n    ->dd();\n")
        ->changedFrom("<?php\n\nreturn \$q\n    ->where(1);\n");

    expect($file->reports(new NoDebugCalls))->toEqual([['line' => 5, 'message' => '->dd() is forbidden. Remove the debug call before committing.']]);
});

it('reports nothing from a check that reads the code of a file that does not parse, as a run leaves the file to NoSyntaxErrors', function (string $contents) {
    $file = TestFile::of(Kind::SOURCE, 'app/Dyn.php', $contents);

    expect($file->reports(new NoEval))->toBe([]);
})->with([
    'a syntax error' => "<?php\n\neval(\$code);\n\nclass Broken {\n",
    'a name imported twice' => "<?php\n\nnamespace App;\n\nuse A\\B;\nuse C\\B;\n\neval(\$code);\n",
]);

it('refuses a violation that gives no message, as a run reports a crash for it', function () {
    $file = TestFile::of(Kind::TEXT, 'README.md', "Clean.\n");

    expect(fn () => $file->reports(new Silent))
        ->toThrow(LogicException::class, 'Silent gives no message. Return one from message(), or pass one to each Violation.')
        ->and(fn () => $file->lines(new Silent))
        ->toThrow(LogicException::class, 'Silent gives no message. Return one from message(), or pass one to each Violation.');
});

it('reports nothing for a change that leaves the contents as they were', function () {
    $file = TestFile::of(Kind::TEXT, 'README.md', "Fast \u{2014} and loose.\n")->changedFrom("Fast \u{2014} and loose.\n");

    expect($file->reports(new NoEmDashes))->toBe([]);
});

it('reports a whole-file violation of a file the change moves, and not the lines it leaves alone', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Other/FooTest.php', "<?php\n\n\$this->dd();\n")->movedFrom('tests/Feature/FooTest.php');

    expect(array_column($file->reports(new TestsInUnitOrFeature), 'line'))->toBe([null])
        ->and($file->lines(new NoEmDashes))->toBe([]);
});

it('judges a file moved in from another kind as new to a check of the kind it moved into', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/Helper.php', "<?php\n\nuse Tests\\TestCase;\n")->movedFrom('tests/Support/Helper.php', Kind::TESTS);

    expect($file->lines(new NoTestCodeInApp))->toBe([3]);
});

it('reports the lines a moved file also changed', function () {
    $file = TestFile::of(Kind::TEXT, 'docs/Huge.md', "Clean.\nNew \u{2014} line.\n")
        ->movedFrom('docs/Big.md')
        ->changedFrom("Clean.\n");

    expect($file->lines(new NoEmDashes))->toBe([2]);
});

it('leaves a violation shown at no line out of the lines', function () {
    $file = TestFile::of(Kind::TESTS, 'tests/Other/FooTest.php', "<?php\n");

    expect($file->lines(new TestsInUnitOrFeature))->toBe([]);
});

it('places the file in a project whose configuration a check reads', function () {
    $root = projectWith(['phpunit.xml' => '<phpunit><testsuites><testsuite name="Integration"><directory>tests/Integration</directory></testsuite></testsuites></phpunit>']);
    $file = TestFile::of(Kind::TESTS, 'tests/Other/FooTest.php', "<?php\n")->in($root);

    expect($file->reports(new TestsInTestSuites))->toEqual([['line' => null, 'message' => 'Move the test into tests/Integration, or add its folder to a test suite in phpunit.xml.']]);
});

it('reports the line a deletion leaves and the one after it', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/A.php', "<?php\n\nclass A\n{\n    public function run(): void\n    {\n    }\n}\n")
        ->changedFrom("<?php\n\nclass A\n{\n    /**\n     * Do it.\n     */\n    public function run(): void\n    {\n    }\n}\n");

    expect($file->lines(new EveryLine))->toBe([4, 5]);
});

it('reports the line after a deleted blank line', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/b.php', "<?php\n\n\$a = [\n    1,\n];\n\$b = 2;\n")
        ->changedFrom("<?php\n\n\$a = [\n    1,\n];\n\n\$b = 2;\n");

    expect($file->lines(new EveryLine))->toBe([5, 6]);
});

it('reports only the last line when the lines after it are deleted', function () {
    $file = TestFile::of(Kind::TEXT, 'README.md', "One.\nTwo.\n")->changedFrom("One.\nTwo.\nThree.\nFour.\n");

    expect($file->lines(new EveryLine))->toBe([2]);
});

it('reports nothing when a change empties the file', function () {
    $file = TestFile::of(Kind::TEXT, 'README.md', '')->changedFrom("One.\nTwo.\n");

    expect($file->lines(new EveryLine))->toBe([]);
});

it('diffs the same lines whatever the diff settings of the developer', function (string $configuration, array $variables) {
    $file = TestFile::of(Kind::SOURCE, 'app/a.php', "<?php\n\n\$a = 10;\ndd(\$a);\n\$b = 2;\n\$c = 3;\n\$d = 40;\ndd(\$d);\n")
        ->changedFrom("<?php\n\n\$a = 1;\ndd(\$a);\n\$b = 2;\n\$c = 3;\n\$d = 4;\n");

    $lines = withEnvironment(hostileGit($configuration, $variables), fn () => $file->lines(new NoDebugCalls));

    expect($lines)->toBe([8]);
})->with([
    'an external diff command' => ["[diff]\n\texternal = /usr/bin/true\n", []],
    'GIT_EXTERNAL_DIFF' => ['', ['GIT_EXTERNAL_DIFF' => '/usr/bin/true']],
    'context between hunks' => ["[diff]\n\tinterHunkContext = 5\n", []],
    'GIT_DIFF_OPTS' => ['', ['GIT_DIFF_OPTS' => '-u5']],
    'the algorithm, the prefixes and the quoting' => ["[diff]\n\talgorithm = patience\n\tnoprefix = true\n\tmnemonicPrefix = true\n[core]\n\tquotepath = true\n", []],
]);
