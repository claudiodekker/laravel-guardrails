<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('fails an allowance that no tracked file is at or under, whichever files the run checks', function (array $arguments) {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', <<<'PHP'
        <?php

        use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

        return [
            (new NoEmDashes)->allowing([
                'docs/legacy.md' => 'It predates the rule.',
                'notes' => 'The notes quote the old style.',
                'removed/old.md' => 'It was deleted.',
            ]),
        ];
        PHP)
        ->write('docs/legacy.md', "Fast \u{2014} and loose.\n")
        ->write('notes/today.md', "Fast \u{2014} and loose.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->runGuardrails(...$arguments));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEmDashes
          removed/old.md allowing() names a path no file matches. Remove it from guardrails.php.

        TEXT);
})->with([
    'every tracked file' => [['--all']],
    'the staged files' => [['--staged']],
    'the changes since the default branch' => [[]],
]);
