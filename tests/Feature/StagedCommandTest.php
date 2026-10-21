<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('checks the staged content when the working copy changes afterwards', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stage('README.md')
        ->stage('guardrails.php')
        ->write('README.md', "Clean.\n")
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain('README.md:1 Replace the em dash');
});

it('names a path with spaces', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('my docs/Big File.md', "Fast \u{2014} and loose.\n")
        ->stage('my docs/Big File.md')
        ->stage('guardrails.php')
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain('my docs/Big File.md:1 Replace the em dash');
});

it('checks a changed file against a committed version', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stage('README.md')
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain('README.md:1 Replace the em dash');
});

it('judges every line of a file git is told not to diff', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('.gitattributes', "README.md -diff\n")
        ->write('README.md', "Fast \u{2014} and loose.\nClean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('README.md', "Fast \u{2014} and loose.\nStill clean.\n")
        ->stageAll()
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain('README.md:1 Replace the em dash');
});

it('judges the same lines whatever the diff settings of the developer', function (string $configuration, array $variables) {
    $result = withEnvironment(hostileGit($configuration, $variables), fn () => ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}}')
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoDebugCalls'])
        ->write('app/a.php', "<?php\n\n\$a = 1;\ndd(\$a);\n\$b = 2;\n\$c = 3;\n\$d = 4;\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('app/a.php', "<?php\n\n\$a = 10;\ndd(\$a);\n\$b = 2;\n\$c = 3;\n\$d = 40;\ndd(\$d);\n")
        ->stageAll()
        ->runGuardrails('--staged')));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoDebugCalls
          app/a.php:8 dd() is forbidden. Remove the debug call before committing.

        TEXT);
})->with([
    'an external diff command' => ["[diff]\n\texternal = /usr/bin/true\n", []],
    'GIT_EXTERNAL_DIFF' => ['', ['GIT_EXTERNAL_DIFF' => '/usr/bin/true']],
    'context between hunks' => ["[diff]\n\tinterHunkContext = 5\n", []],
    'GIT_DIFF_OPTS' => ['', ['GIT_DIFF_OPTS' => '-u5']],
    'the algorithm, the prefixes and the quoting' => ["[diff]\n\talgorithm = patience\n\tnoprefix = true\n\tmnemonicPrefix = true\n[core]\n\tquotepath = true\n", []],
]);

it('judges a changed file by its own lines after a file that changed type', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('a.txt', "one\n")
        ->write('b.txt', "Fast \u{2014} and loose.\nClean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->link('a.txt', 'b.txt')
        ->write('b.txt', "Fast \u{2014} and loose.\nSlow \u{2014} and tight.\n")
        ->stageAll()
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEmDashes
          b.txt:2 Replace the em dash with a comma, a colon, parentheses or a new sentence.

        TEXT);
});

it('judges a changed file beside a submodule the change moves to another commit, whatever format the developer gives submodules', function () {
    $result = withEnvironment(hostileGit("[diff]\n\tsubmodule = log\n"), fn () => ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Clean.\n")
        ->stageAll()
        ->stageSubmodule('lib', str_repeat('1', 40))
        ->commitWithoutHook()
        ->stageSubmodule('lib', str_repeat('2', 40))
        ->write('README.md', "Clean.\nFast \u{2014} and loose.\n")
        ->stage('README.md')
        ->runGuardrails('--staged')));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEmDashes
          README.md:2 Replace the em dash with a comma, a colon, parentheses or a new sentence.

        TEXT);
});

it('leaves a path the merge in progress has not resolved alone', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Base.\n")
        ->write('CHANGELOG.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->branch('feature')
        ->write('README.md', "Ours.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->checkout('feature')
        ->write('README.md', "Theirs.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->checkout('main')
        ->merge('feature')
        ->write('CHANGELOG.md', "Fast \u{2014} and loose.\n")
        ->stage('CHANGELOG.md')
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEmDashes
          CHANGELOG.md:1 Replace the em dash with a comma, a colon, parentheses or a new sentence.

        TEXT);
});

it('judges only what the resolution of a merge in progress adds, not what the other branch committed', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}}')
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoDebugCalls'])
        ->write('app/a.php', "<?php\n\n\$a = 1;\n")
        ->stageAll()
        ->commitWithoutHook()
        ->branch('feature')
        ->write('app/a.php', "<?php\n\n\$a = 2;\n")
        ->stageAll()
        ->commitWithoutHook()
        ->checkout('feature')
        ->write('app/a.php', "<?php\n\n\$a = 3;\n")
        ->write('app/b.php', "<?php\n\ndd(1);\n")
        ->stageAll()
        ->commitWithoutHook()
        ->checkout('main')
        ->merge('feature')
        ->write('app/a.php', "<?php\n\n\$a = 5;\ndd(\$a);\n")
        ->stage('app/a.php')
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoDebugCalls
          app/a.php:4 dd() is forbidden. Remove the debug call before committing.

        TEXT);
});

it('reads the staged phpunit.xml and composer.json, not the working copies', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select([
            'ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Structure\\NoTestCodeInApp',
            'ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Tests\\TestsInTestSuites',
        ])
        ->write('composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}, "autoload-dev": {"psr-4": {"Tests\\\\": "tests/"}}}')
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Unit"><directory>tests/Unit</directory></testsuite></testsuites></phpunit>')
        ->stageAll()
        ->commitWithoutHook()
        ->write('composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}, "autoload-dev": {"psr-4": {"Acme\\\\Testing\\\\": "tests/"}}}')
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Integration"><directory>tests/Integration</directory></testsuite></testsuites></phpunit>')
        ->write('app/Clock.php', "<?php\n\nuse Acme\\Testing\\FakeClock;\n")
        ->write('tests/Integration/ApiTest.php', "<?php\n\nit('works', fn () => true);\n")
        ->stageAll()
        ->write('composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}, "autoload-dev": {"psr-4": {"Tests\\\\": "tests/"}}}')
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Unit"><directory>tests/Unit</directory></testsuite></testsuites></phpunit>')
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoTestCodeInApp
          app/Clock.php:3 Keep the namespaces autoload-dev maps out of application code, because they are only autoloaded in development.

        TEXT);
});

it('checks the staged files of a project in a subfolder of the repository by its own staged configuration', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Root"><directory>tests</directory></testsuite></testsuites></phpunit>')
        ->directory('backend')
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Tests\\TestsInTestSuites'])
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Integration"><directory>tests/Integration</directory></testsuite></testsuites></phpunit>')
        ->write('tests/ExampleTest.php', "<?php\n\nit('works', fn () => true);\n")
        ->write('tests/Integration/ApiTest.php', "<?php\n\nit('works', fn () => true);\n")
        ->stageAll()
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Everything"><directory>tests</directory></testsuite></testsuites></phpunit>')
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        TestsInTestSuites
          tests/ExampleTest.php Move the test into tests/Integration, or add its folder to a test suite in phpunit.xml.

        TEXT);
});

it('leaves no staged copy of guardrails.php behind, even when it exits the process', function (string $code) {
    $leftovers = ScratchRepository::using(function (ScratchRepository $repository) use ($code) {
        $repository
            ->write('guardrails.php', $code)
            ->write('README.md', "Clean.\n")
            ->stageAll()
            ->runGuardrails('--staged');

        return glob($repository->path.'/.guardrails-staged-*');
    });

    expect($leftovers)->toBe([]);
})->with([
    'a file that exits' => ["<?php\n\nexit(0);\n"],
    'a file that returns' => ["<?php\n\nreturn [];\n"],
    'a file that throws' => ["<?php\n\nthrow new RuntimeException('Nope.');\n"],
]);

it('fails with exit code 2 when the project folder is not writable, instead of staging the copy elsewhere', function () {
    $result = ScratchRepository::using(function (ScratchRepository $repository) {
        $repository
            ->select()
            ->write('README.md', "Clean.\n")
            ->stageAll();

        chmod($repository->path, 0555);

        try {
            return $repository->runGuardrails('--staged');
        } finally {
            chmod($repository->path, 0755);
        }
    });

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toContain('is not writable, so the staged guardrails.php cannot be loaded from there.');
})->skip(fn () => function_exists('posix_geteuid') && posix_geteuid() === 0, 'root can write to any folder.');
