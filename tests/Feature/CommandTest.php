<?php

use ClaudioDekker\LaravelGuardrails\Process;
use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('reports every failure as path:line message, grouped per check, and exits with one', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->write('app/Models/User.php', "<?php\n\nclass User {}\n\nclass Team {}\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEmDashes
          README.md:1 Replace the em dash with a comma, a colon, parentheses or a new sentence.

        OneClassPerFile
          app/Models/User.php:5 Move the class into its own file, named after it.

        TEXT);
});

it('reports a PHP file that does not parse at the line of the error, under NoSyntaxErrors, after the selected checks', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->write('app/Broken.php', "<?php\n\nclass Broken\n{\n    public function () {}\n}\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEmDashes
          README.md:1 Replace the em dash with a comma, a colon, parentheses or a new sentence.

        NoSyntaxErrors
          app/Broken.php:5 Syntax error, unexpected '('. Fix it so the file parses, then run the checks again.

        TEXT);
});

it('fails a PHP file that does not parse even when no selected check reads PHP', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes'])
        ->write('app/Broken.php', "<?php\n\nclass Broken\n{\n    public function () {}\n}\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoSyntaxErrors
          app/Broken.php:5 Syntax error, unexpected '('. Fix it so the file parses, then run the checks again.

        TEXT);
});

it('leaves the files NoSyntaxErrors allows alone in every check, and still fails the other files that do not parse', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', <<<'PHP'
        <?php

        use ClaudioDekker\LaravelGuardrails\Checks\Php\NoSyntaxErrors;
        use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\OneClassPerFile;

        return [
            OneClassPerFile::class,
            (new NoSyntaxErrors)->allowing(['tests/Fixtures/broken' => 'The parser tests read files that do not parse.']),
        ];

        PHP)
        ->write('tests/Fixtures/broken/Unclosed.php', "<?php\n\nclass Unclosed\n{\n    public function () {}\n}\n\nclass Second {}\n")
        ->write('app/Broken.php', "<?php\n\nclass Broken\n{\n    public function () {}\n}\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoSyntaxErrors
          app/Broken.php:5 Syntax error, unexpected '('. Fix it so the file parses, then run the checks again.

        TEXT);
});

it('reads a Blade template as text, not as PHP code', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('resources/views/welcome.blade.php', "<?php class First {} class Second {} ?>\n<p>Fast \u{2014} and loose.</p>\n<?php \$a = ?>\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEmDashes
          resources/views/welcome.blade.php:2 Replace the em dash with a comma, a colon, parentheses or a new sentence.

        TEXT);
});

it('passes a clean repository and says what it covered', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('app/Models/User.php', "<?php\n\nclass User {}\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->successful())->toBeTrue()
        ->and($result->output)->toBe("Guardrails passed: 7 checks over 2 files (every tracked file).\n")
        ->and($result->errorOutput)->toBe('');
});

it('counts the checks guardrails.php selects, so NoSyntaxErrors only when it lists it', function (string $entries, string $checks) {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', "<?php\n\nreturn [\n{$entries}];\n")
        ->write('README.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->output)->toBe("Guardrails passed: {$checks} over 2 files (every tracked file).\n");
})->with([
    'not listed' => ["    \\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes::class,\n", '1 check'],
    'listed' => ["    \\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes::class,\n    \\ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\NoSyntaxErrors::class,\n", '2 checks'],
]);

it('checks every tracked file, not only the staged ones', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('committed.md', "Fast \u{2014} and loose.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('untracked.md', "Fast \u{2014} and loose.\n")
        ->runGuardrails('--all'));

    expect($result->errorOutput)->toContain('committed.md:1');
    expect($result->errorOutput)->not->toContain('untracked.md');
});

it('checks the project in a subfolder of the repository by its own paths and configuration', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('docs/README.md', "Fast \u{2014} and loose.\n")
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Root"><directory>tests</directory></testsuite></testsuites></phpunit>')
        ->directory('backend')
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Tests\\TestsInTestSuites'])
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Integration"><directory>tests/Integration</directory></testsuite></testsuites></phpunit>')
        ->write('app/Models/User.php', "<?php\n\nclass User {}\n\nclass Team {}\n")
        ->write('tests/ExampleTest.php', "<?php\n\nit('works', fn () => true);\n")
        ->write('tests/Integration/ApiTest.php', "<?php\n\nit('works', fn () => true);\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        TestsInTestSuites
          tests/ExampleTest.php Move the test into tests/Integration, or add its folder to a test suite in phpunit.xml.

        TEXT);
});

it('checks a repository of thousands of files', function () {
    $result = ScratchRepository::using(function (ScratchRepository $repository) {
        foreach (range(1, 4000) as $number) {
            $repository->write("docs/chapters/a-chapter-with-a-long-name-{$number}.md", "Clean.\n");
        }

        return $repository->select()->stageAll()->commitWithoutHook()->runGuardrails('--all');
    });

    expect($result->errorOutput)->toBe('')
        ->and($result->output)->toBe("Guardrails passed: 7 checks over 4001 files (every tracked file).\n");
});

it('checks thousands of PHP files within a small memory limit', function () {
    $methods = implode("\n", array_map(fn (int $number) => "    public function method{$number}(int \$value): int\n    {\n        return \$value * {$number} + strlen((string) \$value);\n    }\n", range(1, 40)));

    $result = ScratchRepository::using(function (ScratchRepository $repository) use ($methods) {
        foreach (range(1, 3000) as $number) {
            $repository->write("app/Services/Service{$number}.php", "<?php\n\nnamespace App\\Services;\n\nclass Service{$number}\n{\n{$methods}}\n");
        }

        return $repository->select()->stageAll()->commitWithoutHook()->runGuardrailsWithMemoryLimit('128M', '--all');
    });

    expect($result->errorOutput)->toBe('')
        ->and($result->output)->toBe("Guardrails passed: 7 checks over 3001 files (every tracked file).\n");
});

it('prints the split steps once under the files over the token budget', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->writeBytes('One.php', 24000)
        ->writeBytes('Two.php', 40000)
        ->writeBytes('Edge.php', 20000)
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toStartWith(<<<'TEXT'
        FilesWithinTokenBudget
          One.php Split the file into files under 5000 tokens (20000 bytes) each.
          Two.php Split the file into files under 5000 tokens (20000 bytes) each.

          1. Group the file's contents by the one job each part does.
        TEXT)
        ->and(substr_count($result->errorOutput, 'Do not shrink a file'))->toBe(1)
        ->and($result->errorOutput)->not->toContain('Edge.php');
});

it('tells an enum over the token budget to move its data out instead of splitting it', function () {
    $cases = implode('', array_map(fn (int $number) => "    case Plan{$number} = 'plan-{$number}';\n", range(1, 800)));

    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('app/Enums/Plan.php', "<?php\n\nnamespace App\\Enums;\n\nenum Plan: string\n{\n{$cases}}\n")
        ->writeBytes('One.php', 24000)
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toStartWith(<<<'TEXT'
        FilesWithinTokenBudget
          One.php Split the file into files under 5000 tokens (20000 bytes) each.
          app/Enums/Plan.php Move the data of the enum's cases out of the enum, for example into a database table or a config file, until the file is under 5000 tokens (20000 bytes).

          1. Group the file's contents by the one job each part does.
        TEXT)
        ->and(substr_count($result->errorOutput, 'Do not shrink a file'))->toBe(1);
});

it('skips generated and binary files', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('.gitattributes', "*.min.js linguist-generated\n")
        ->write('public/app.min.js', str_repeat("\u{2014}", 10000))
        ->write('image.bin', str_repeat("\0", 40000))
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->successful())->toBeTrue()
        ->and($result->errorOutput)->toBe('');
});

it('skips lockfiles without a generated mark', function () {
    $result = ScratchRepository::using(function (ScratchRepository $repository) {
        foreach (['bun.lock', 'bun.lockb', 'composer.lock', 'frontend/package-lock.json', 'package-lock.json', 'pnpm-lock.yaml', 'yarn.lock'] as $lockfile) {
            $repository->write($lockfile, str_repeat("\u{2014}", 10000));
        }

        return $repository->select()->stageAll()->commitWithoutHook()->runGuardrails('--all');
    });

    expect($result->errorOutput)->toBe('')
        ->and($result->output)->toBe("Guardrails passed: 7 checks over 1 file (every tracked file).\n");
});

it('counts only the files it reads, leaving out lockfiles and binary, generated and vendor files', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('.gitattributes', "*.min.js linguist-generated\n")
        ->write('composer.lock', "{}\n")
        ->write('image.bin', "\0\0\0")
        ->write('public/app.min.js', "run();\n")
        ->write('vendor/acme/package/src/Thing.php', "<?php\n")
        ->write('README.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->errorOutput)->toBe('')
        ->and($result->output)->toBe("Guardrails passed: 7 checks over 3 files (every tracked file).\n");
});

it('still checks a file whose generated mark is unset', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('.gitattributes', "*.lock linguist-generated\nkeep.lock -linguist-generated\n")
        ->writeBytes('keep.lock', 40000)
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain('keep.lock Split');
});

it('passes and points at the install command when no checks are selected', function (?string $composerJson, ?string $guardrailsPhp) {
    $result = ScratchRepository::using(function (ScratchRepository $repository) use ($composerJson, $guardrailsPhp) {
        if ($composerJson !== null) {
            $repository->write('composer.json', $composerJson);
        }

        if ($guardrailsPhp !== null) {
            $repository->write('guardrails.php', $guardrailsPhp);
        }

        return $repository
            ->write('README.md', "Fast \u{2014} and loose.\n")
            ->stageAll()
            ->commitWithoutHook()
            ->runGuardrails('--all');
    });

    expect($result->successful())->toBeTrue()
        ->and($result->output)->toBe("No guardrails selected. Run vendor/bin/guardrails install to choose them.\n")
        ->and($result->errorOutput)->toBe('');
})->with([
    'no composer.json and no guardrails.php' => [null, null],
    'no guardrails key' => ['{"name": "acme/app"}', null],
    'an empty guardrails.php' => [null, "<?php\n\nreturn [];\n"],
]);

it('fails when a selected check no longer exists, naming the class and both fixes', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select(['Acme\\Pack\\NoFoo'])
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toBe("Check class Acme\\Pack\\NoFoo does not exist. Require the package that provides it again, or run vendor/bin/guardrails install to deselect it.\n");
});

it('fails when a selected check comes from a package composer.json does not discover', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes'], ['dont-discover' => ['*']])
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toBe("ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes comes from claudiodekker/laravel-guardrails, which extra.guardrails.dont-discover ignores through its entry *. Remove * from dont-discover, or run vendor/bin/guardrails install to deselect it.\n");
});

it('refuses an unknown argument', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository->runGuardrails('--everything'));

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toBe("Unknown argument --everything. Usage: guardrails [--staged | --all | --base=<ref>] [--help] [--version], guardrails list [--details], or guardrails install [--preset=<name>] [--check=<class>]...\n");
});

it('refuses to run outside a git repository', function () {
    $directory = sys_get_temp_dir().'/guardrails-plain-'.bin2hex(random_bytes(6));
    mkdir($directory);
    $directory = (string) realpath($directory);

    $result = Process::run($directory, [PHP_BINARY, dirname(__DIR__, 2).'/bin/guardrails']);
    rmdir($directory);

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toBe("{$directory} is not inside a git repository.\n");
});
