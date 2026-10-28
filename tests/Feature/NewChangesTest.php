<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('passes an existing violation in a file the change leaves alone', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->write('CONTRIBUTING.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails());

    expect($result->successful())->toBeTrue()
        ->and($result->output)->toBe("Guardrails passed: 7 checks over 1 file (origin/HEAD..HEAD).\n");
});

it('passes an existing violation on a line the change leaves alone', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->write('README.md', "Fast \u{2014} and loose.\nClean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails());

    expect($result->successful())->toBeTrue();
});

it('fails a violation on a line the change adds or changes', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "One.\nTwo \u{2014} old.\nThree.\nFour.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->write('README.md', "One \u{2014} changed.\nTwo \u{2014} old.\nThree.\nFour.\nFive \u{2014} added.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails());

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEmDashes
          README.md:1 Replace the em dash with a comma, a colon, parentheses or a new sentence.
          README.md:5 Replace the em dash with a comma, a colon, parentheses or a new sentence.

        TEXT);
});

it('fails a new file with two classes', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->commitWithoutHook()
        ->publish()
        ->write('app/Example.php', "<?php\n\nclass First {}\n\nclass Second {}\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails());

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain("OneClassPerFile\n  app/Example.php:5 Move the class into its own file");
});

it('fails a test file the change adds outside the test folders', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->commitWithoutHook()
        ->publish()
        ->write('tests/Http/ExampleTest.php', "<?php\n\nit('works', function () {});\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails());

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain("TestsInTestSuites\n  tests/Http/ExampleTest.php Move the test");
});

it('fails a debug call the change appends to an existing chain, at the line of the call', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}, "autoload-dev": {"psr-4": {"Tests\\\\": "tests/"}}}')
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoDebugCalls'])
        ->write('app/a.php', "<?php\n\nreturn \$q\n    ->where(1);\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('app/a.php', "<?php\n\nreturn \$q\n    ->where(1)\n    ->dd();\n")
        ->stageAll()
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoDebugCalls
          app/a.php:5 ->dd() is forbidden. Remove the debug call before committing.

        TEXT);
});

it('fails a test the change moves out of its suite folder', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}, "autoload-dev": {"psr-4": {"Tests\\\\": "tests/"}}}')
        ->select([
            'ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Tests\\TestsInTestSuites',
            'ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Tests\\TestsInUnitOrFeature',
        ])
        ->write('tests/Feature/FooTest.php', "<?php\n\nit('works', function () {});\n")
        ->stageAll()
        ->commitWithoutHook()
        ->move('tests/Feature/FooTest.php', 'tests/Other/FooTest.php')
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        TestsInTestSuites
          tests/Other/FooTest.php Move the test into tests/Unit, tests/Feature or tests/Browser, or add a phpunit.xml whose test suites run its folder.

        TestsInUnitOrFeature
          tests/Other/FooTest.php Move the test into a Unit or Feature folder.

        TEXT);
});

it('fails a config key the change inserts above a key that sorts before it', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}, "autoload-dev": {"psr-4": {"Tests\\\\": "tests/"}}}')
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Config\\SortedConfigKeys'])
        ->write('config/x.php', "<?php\n\nreturn [\n    'a' => 1,\n    'b' => 1,\n];\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('config/x.php', "<?php\n\nreturn [\n    'a' => 1,\n    'c' => 1,\n    'b' => 1,\n];\n")
        ->stageAll()
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        SortedConfigKeys
          config/x.php:6 Move 'b' above 'c'.

        TEXT);
});

it('passes an oversized file the change only renames, even when git is set not to detect renames', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->config('diff.renames', 'false')
        ->writeBytes('docs/Big.md', 30000)
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->move('docs/Big.md', 'docs/Huge.md')
        ->commitWithoutHook()
        ->runGuardrails());

    expect($result->successful())->toBeTrue()
        ->and($result->output)->toBe("Guardrails passed: 7 checks over 1 file (origin/HEAD..HEAD).\n");
});

it('passes a test outside its suite that the change only renames, even when git is set not to detect renames', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->config('diff.renames', 'false')
        ->write('tests/Http/ExampleTest.php', "<?php\n\nit('works', function () {});\n")
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->move('tests/Http/ExampleTest.php', 'tests/Http/RenamedTest.php')
        ->commitWithoutHook()
        ->runGuardrails());

    expect($result->errorOutput)->toBe('')
        ->and($result->output)->toBe("Guardrails passed: 7 checks over 1 file (origin/HEAD..HEAD).\n");
});

it('fails a file over the token budget that the change grows', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('resources/js/pages/Welcome.vue', str_repeat("<p>Welcome</p>\n", 2000))
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->write('resources/js/pages/Welcome.vue', str_repeat("<p>Welcome</p>\n", 2001))
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails());

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain("FilesWithinTokenBudget\n  resources/js/pages/Welcome.vue Split the file");
});

it('passes a file over the token budget that the change touches without growing it', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('resources/js/pages/Welcome.vue', str_repeat("<p>Welcome</p>\n", 2000))
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->write('resources/js/pages/Welcome.vue', "<p>Hello</p>\n".str_repeat("<p>Welcome</p>\n", 1999))
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails());

    expect($result->successful())->toBeTrue();
});

it('judges only the staged part of a partly staged change', function (string $staged, int $exitCode) {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Old \u{2014} line.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('README.md', $staged)
        ->stage('README.md')
        ->write('README.md', "Old \u{2014} line.\nClean.\nNew \u{2014} line.\n")
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe($exitCode);
})->with([
    'the clean line' => ["Old \u{2014} line.\nClean.\n", 0],
    'the line with an em dash' => ["Old \u{2014} line.\nNew \u{2014} line.\n", 1],
]);

it('compares against the branch given as the base', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->commitWithoutHook()
        ->branch('develop')
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--base=develop'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain('README.md:1 Replace the em dash');
});

it('checks nothing and says why when the repository has no default branch to compare against', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails());

    expect($result->successful())->toBeTrue()
        ->and($result->output)->toBe("Guardrails checked nothing: origin/HEAD does not exist. Pass --base=<ref> to compare against another branch.\n")
        ->and($result->errorOutput)->toBe('');
});

it('fails on CI when the repository has no default branch to compare against, saying why on the error stream', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrailsOn(['CI=true']));

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe("Guardrails checked nothing: origin/HEAD does not exist. Pass --base=<ref> to compare against another branch.\n");
});

it('fails when the base it is given does not exist', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--base=origin/main'));

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe("origin/main does not exist.\n");
});

it('fails when the base it is given shares no history with HEAD', function () {
    $result = ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->commitWithoutHook()->git('checkout', '--quiet', '--orphan', 'unrelated');

        return $repository
            ->select()
            ->write('README.md', "Fast \u{2014} and loose.\n")
            ->stageAll()
            ->commitWithoutHook()
            ->runGuardrails('--base=main');
    });

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe("HEAD shares no history with main.\n");
});

it('passes an unchanged Laravel skeleton against its default branch and with nothing staged', function (array $arguments, string $scope) {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->copy(__DIR__.'/../Fixtures/skeleton')
        ->selectPreset('opinionated')
        ->writeBytes('resources/js/pages/Welcome.vue', 60000)
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->runGuardrails(...$arguments));

    expect($result->successful())->toBeTrue()
        ->and($result->output)->toBe("Guardrails passed: 35 checks over 0 files ({$scope}).\n");
})->with([
    'against the default branch' => [[], 'origin/HEAD..HEAD'],
    'staged' => [['--staged'], 'staged changes'],
]);

it('fails a file the change moves out of an allowed folder, as it fails a copy of it', function (string $change) {
    $result = ScratchRepository::using(function (ScratchRepository $repository) use ($change) {
        $repository
            ->write('composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}}')
            ->write('guardrails.php', "<?php\n\nreturn [\n    (new \\ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval)->allowing(['app/Legacy' => 'It predates the rule.']),\n];\n")
            ->write('app/Legacy/Dyn.php', "<?php\n\nfunction run(\$code)\n{\n    eval(\$code);\n}\n")
            ->write('app/Legacy/Kept.php', "<?php\n\nfunction kept() {}\n")
            ->stageAll()
            ->commitWithoutHook();

        if ($change === 'a move') {
            $repository->move('app/Legacy/Dyn.php', 'app/Dyn.php');
        } else {
            $repository->write('app/Dyn.php', "<?php\n\nfunction run(\$code)\n{\n    eval(\$code);\n}\n")->stageAll();
        }

        return $repository->runGuardrails('--staged');
    });

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEval
          app/Dyn.php:5 Replace eval() with code that does the same, because it runs whatever string it is given.

        TEXT);
})->with(['a move', 'a copy']);

it('reads the phpunit.xml of HEAD, not the working copy, against a base', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Tests\\TestsInTestSuites'])
        ->commitWithoutHook()
        ->branch('base')
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Integration"><directory>tests/Integration</directory></testsuite></testsuites></phpunit>')
        ->write('tests/Integration/ApiTest.php', "<?php\n\nit('works', fn () => true);\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Unit"><directory>tests/Unit</directory></testsuite></testsuites></phpunit>')
        ->runGuardrails('--base=base'));

    expect($result->errorOutput)->toBe("Guardrails read the commits only, so the uncommitted changes were not checked. Commit them, or run guardrails --staged.\n")
        ->and($result->successful())->toBeTrue();
});

it('checks the committed files of a project in a subfolder of the repository by its own committed configuration', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->commitWithoutHook()
        ->branch('base')
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Root"><directory>tests</directory></testsuite></testsuites></phpunit>')
        ->directory('backend')
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Tests\\TestsInTestSuites'])
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Integration"><directory>tests/Integration</directory></testsuite></testsuites></phpunit>')
        ->write('tests/ExampleTest.php', "<?php\n\nit('works', fn () => true);\n")
        ->write('tests/Integration/ApiTest.php', "<?php\n\nit('works', fn () => true);\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Everything"><directory>tests</directory></testsuite></testsuites></phpunit>')
        ->runGuardrails('--base=base'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        TestsInTestSuites
          tests/ExampleTest.php Move the test into tests/Integration, or add its folder to a test suite in phpunit.xml.
        Guardrails read the commits only, so the uncommitted changes were not checked. Commit them, or run guardrails --staged.

        TEXT);
});

it('reads the phpunit.xml on disk, not the committed one, over every tracked file', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Tests\\TestsInTestSuites'])
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Unit"><directory>tests/Unit</directory></testsuite></testsuites></phpunit>')
        ->write('tests/Integration/ApiTest.php', "<?php\n\nit('works', fn () => true);\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Integration"><directory>tests/Integration</directory></testsuite></testsuites></phpunit>')
        ->runGuardrails('--all'));

    expect($result->errorOutput)->toBe('')
        ->and($result->successful())->toBeTrue();
});
