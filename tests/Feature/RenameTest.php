<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('pairs a renamed and edited file with its old path, whatever rename limit git is set to', function () {
    $lines = implode('', array_map(fn (int $number) => "Line {$number} of the guide.\n", range(1, 20)));

    $result = withEnvironment(hostileGit("[diff]\n\trenameLimit = 1\n"), fn () => ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('docs/Guide.md', "Fast \u{2014} and loose.\n{$lines}")
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->move('docs/Guide.md', 'docs/Manual.md')
        ->write('docs/Manual.md', "Fast \u{2014} and loose.\n{$lines}One more line.\n")
        ->write('docs/Index.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails()));

    expect($result->errorOutput)->toBe('')
        ->and($result->output)->toBe("Guardrails passed: 7 checks over 2 files (origin/HEAD..HEAD).\n");
});

it('judges a file moved out of a generated folder as a new file, because no check read it there', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('.gitattributes', "dist/** linguist-generated\n")
        ->write('dist/guide.md', "Fast \u{2014} and loose.\nClean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->move('dist/guide.md', 'docs/guide.md')
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEmDashes
          docs/guide.md:1 Replace the em dash with a comma, a colon, parentheses or a new sentence.

        TEXT);
});
