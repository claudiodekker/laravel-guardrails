<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('reads the regular files git tracks over every tracked file, dotfiles included, never an untracked file or a symbolic link', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select([
            'ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes',
            'ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Tests\\TestsInTestSuites',
        ])
        ->write('.github/CONTRIBUTING.md', "Fast \u{2014} and loose.\n")
        ->write('docs/guide.md', "Slow \u{2014} and tight.\n")
        ->write('docs/link.md', '')
        ->link('docs/link.md', 'guide.md')
        ->write('tests/Unit/ExampleTest.php', "<?php\n\nit('works', fn () => true);\n")
        ->stageAll()
        ->commitWithoutHook()
        ->write('phpunit.xml', '<phpunit><testsuites><testsuite name="Integration"><directory>tests/Integration</directory></testsuite></testsuites></phpunit>')
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEmDashes
          .github/CONTRIBUTING.md:1 Replace the em dash with a comma, a colon, parentheses or a new sentence.
          docs/guide.md:1 Replace the em dash with a comma, a colon, parentheses or a new sentence.

        TEXT);
});
