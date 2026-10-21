<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('refuses a commit with an em dash and names the line and the fix', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository->select()->installHook()->withGuardrailsBinary()
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stage('README.md')
        ->stage('guardrails.php')
        ->commit());

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain("NoEmDashes\n  README.md:1 Replace the em dash with a comma, a colon, parentheses or a new sentence.");
});

it('refuses a commit with a file over the token budget and gives the split steps', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository->select()->installHook()->withGuardrailsBinary()
        ->writeBytes('src/Big.php', 20001)
        ->stage('src/Big.php')
        ->stage('guardrails.php')
        ->commit());

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)
        ->toContain('src/Big.php Split the file into files under 5000 tokens (20000 bytes) each.')
        ->toContain("1. Group the file's contents by the one job each part does.");
});

it('lets a clean commit through', function () {
    $log = ScratchRepository::using(function (ScratchRepository $repository) {
        $result = $repository->select()->installHook()->withGuardrailsBinary()
            ->write('app/Models/User.php', "<?php\n\nclass User {}\n")
            ->stage('app/Models/User.php')
            ->stage('guardrails.php')
            ->commit();

        expect($result->successful())->toBeTrue()
            ->and($result->errorOutput)->toBe("Guardrails passed: 7 checks over 2 files (staged changes).\n");

        return $repository->git('log', '--format=%s');
    });

    expect($log)->toBe("Change\n");
});

it('judges the staged content, not the working copy', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository->select()->installHook()->withGuardrailsBinary()
        ->write('README.md', "Clean.\n")
        ->stage('README.md')
        ->stage('guardrails.php')
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->commit());

    expect($result->successful())->toBeTrue();
});

it('refuses to commit until composer install has put the binary in place', function () {
    $result = ScratchRepository::using(function (ScratchRepository $repository) {
        return $repository
            ->select()
            ->installHook()
            ->write('README.md', "Clean.\n")
            ->stage('README.md')
            ->commit();
    });

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain('vendor/bin/guardrails is missing. Run composer install, then commit again.');
});

it('runs in a linked worktree through the shared hooks directory', function () {
    $result = ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->select()->installHook()->withGuardrailsBinary()->stageAll()->commitWithoutHook();

        return $repository->worktree('feature')
            ->withGuardrailsBinary()
            ->write('README.md', "Fast \u{2014} and loose.\n")
            ->stage('README.md')
            ->commit();
    });

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain('README.md:1 Replace the em dash');
});

it('runs the checks of a project in a subfolder from that folder, by the paths of the project', function () {
    $result = ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->directory('backend')
            ->select()
            ->write('tests/ExampleTest.php', "<?php\n\nit('works', fn () => true);\n")
            ->installHook()
            ->withGuardrailsBinary();

        return $repository->write('docs/README.md', "Fast \u{2014} and loose.\n")->stageAll()->commit();
    });

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        TestsInTestSuites
          tests/ExampleTest.php Move the test into tests/Unit, tests/Feature or tests/Browser, or add a phpunit.xml whose test suites run its folder.

        TEXT);
});
