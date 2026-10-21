<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;
use Composer\InstalledVersions;

it('refuses to combine --staged, --all and --base', function (array $arguments) {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->runGuardrails(...$arguments));

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toStartWith('Use only one of --staged, --all and --base=<ref>. Usage: guardrails');
})->with([
    'staged and all' => [['--staged', '--all']],
    'all and a base' => [['--all', '--base=origin/main']],
    'a base and staged' => [['--base=origin/main', '--staged']],
    'all three' => [['--staged', '--all', '--base=origin/main']],
]);

it('refuses an empty --base', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->runGuardrails('--base='));

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toStartWith('--base needs a ref, such as --base=origin/main. Usage: guardrails');
});

it('refuses a positional argument', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->runGuardrails('app/Models/User.php'));

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toStartWith('Unknown argument app/Models/User.php. Usage: guardrails');
});

it('prints its usage with --help and exits with zero', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository->runGuardrails('--help'));

    expect($result->exitCode)->toBe(0)
        ->and($result->output)->toStartWith('Usage: guardrails [--staged | --all | --base=<ref>]')
        ->and($result->errorOutput)->toBe('');
});

it('prints the version Composer reports with --version and exits with zero', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository->runGuardrails('--version'));

    expect($result->exitCode)->toBe(0)
        ->and($result->output)->toBe(InstalledVersions::getPrettyVersion('claudiodekker/laravel-guardrails')."\n")
        ->and($result->errorOutput)->toBe('');
});

it('fails on CI when no check is selected, because a run that checks nothing must not pass', function (?string $guardrailsPhp) {
    $result = ScratchRepository::using(function (ScratchRepository $repository) use ($guardrailsPhp) {
        if ($guardrailsPhp !== null) {
            $repository->write('guardrails.php', $guardrailsPhp);
        }

        return $repository
            ->write('README.md', "Fast \u{2014} and loose.\n")
            ->stageAll()
            ->commitWithoutHook()
            ->runGuardrailsOn(['CI=true'], '--all');
    });

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe("No guardrails selected. Run vendor/bin/guardrails install to choose them.\n");
})->with([
    'no guardrails.php' => [null],
    'an empty guardrails.php' => ["<?php\n\nreturn [];\n"],
]);

it('names what a pass checked: the staged changes, the range from the base, or every tracked file', function (array $arguments, string $scope) {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Fine.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->branch('feature')
        ->checkout('feature')
        ->write('NOTES.md', "Fine too.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails(...$arguments));

    expect($result->exitCode)->toBe(0)
        ->and($result->output)->toEndWith("({$scope}).\n");
})->with([
    'staged' => [['--staged'], 'staged changes'],
    'a base' => [['--base=main'], 'main..HEAD'],
    'all' => [['--all'], 'every tracked file'],
]);

it('says when a run over commits left the uncommitted changes unread, and not when it reads the index', function (array $arguments, bool $says) {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Fine.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->branch('feature')
        ->checkout('feature')
        ->write('README.md', "Still fine.\n")
        ->runGuardrails(...$arguments));

    expect($result->exitCode)->toBe(0)
        ->and($result->errorOutput)->toBe($says ? "Guardrails read the commits only, so the uncommitted changes were not checked. Commit them, or run guardrails --staged.\n" : '');
})->with([
    'against the base' => [['--base=main'], true],
    'over the staged changes' => [['--staged'], false],
    'over every tracked file' => [['--all'], false],
]);

it('says nothing about uncommitted changes when there are none', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Fine.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->branch('feature')
        ->checkout('feature')
        ->runGuardrails('--base=main'));

    expect($result->errorOutput)->toBe('');
});

it('reads guardrails.php from the index when staged, so an edit that is not staged cannot change the verdict', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', "<?php\n\nreturn [\n    \\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes::class,\n];\n")
        ->write('README.md', "Fine.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stage('README.md')
        ->write('guardrails.php', "<?php\n\nreturn [\n    (new \\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes)->allowing(['README.md' => 'Not staged.']),\n];\n")
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe("NoEmDashes\n  README.md:1 Replace the em dash with a comma, a colon, parentheses or a new sentence.\n");
});

it('reads guardrails.php from disk when staged and git does not track it yet', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('README.md', "Fine.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('guardrails.php', "<?php\n\nreturn [\n    \\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes::class,\n];\n")
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stage('README.md')
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain('README.md:1');
});

it('runs the staged guardrails.php from the project, so __DIR__ points at it', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails/checks.php', "<?php\n\nreturn [\\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes::class];\n")
        ->write('guardrails.php', "<?php\n\nreturn require __DIR__.'/guardrails/checks.php';\n")
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stageAll()
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain('README.md:1');
});

it('reads .gitattributes from the index when staged', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes'])
        ->write('README.md', "Fine.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stage('README.md')
        ->write('.gitattributes', "README.md linguist-generated\n")
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain('README.md:1');
});

it('refuses a mode flag or a base given twice', function (array $arguments, string $message) {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository->select()->runGuardrails(...$arguments));

    expect($result->exitCode)->toBe(2)
        ->and(str_starts_with($result->errorOutput, $message))->toBeTrue();
})->with([
    'all twice' => [['--all', '--all'], 'Pass --all once.'],
    'staged twice' => [['--staged', '--staged'], 'Pass --staged once.'],
    'base twice' => [['--base=main', '--base=develop'], 'Pass --base once.'],
    'base twice, the same ref' => [['--base=main', '--base=main'], 'Pass --base once.'],
]);
