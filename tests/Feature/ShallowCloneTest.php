<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('says to fetch the full history when a shallow clone shares no history with the branch to compare against', function () {
    [$default, $base] = ScratchRepository::using(function (ScratchRepository $repository) {
        $repository
            ->select()
            ->write('README.md', "One.\n")
            ->stageAll()
            ->commitWithoutHook()
            ->write('README.md', "Two.\n")
            ->stageAll()
            ->commitWithoutHook()
            ->branch('feature')
            ->checkout('feature')
            ->write('README.md', "Three.\n")
            ->stageAll()
            ->commitWithoutHook();

        $clone = $repository->shallowClone('feature');
        $clone->git('fetch', '--quiet', '--depth=1', 'origin', 'main:refs/remotes/origin/main');
        $clone->git('remote', 'set-head', 'origin', 'main');

        return [$clone->runGuardrails(), $clone->runGuardrails('--base=origin/main')];
    });

    expect($default->exitCode)->toBe(0)
        ->and($default->output)->toBe("Guardrails checked nothing: HEAD shares no history with origin/HEAD. Fetch the full history, as fetch-depth: 0 does for actions/checkout.\n")
        ->and($base->exitCode)->toBe(2)
        ->and($base->errorOutput)->toBe("HEAD shares no history with origin/main. Fetch the full history, as fetch-depth: 0 does for actions/checkout.\n");
});

it('gives the fix in one go when a shallow clone has no origin/HEAD to compare against', function () {
    [$default, $ci] = ScratchRepository::using(function (ScratchRepository $repository) {
        $repository
            ->select()
            ->write('README.md', "One.\n")
            ->stageAll()
            ->commitWithoutHook()
            ->branch('feature')
            ->checkout('feature')
            ->write('README.md', "Two.\n")
            ->stageAll()
            ->commitWithoutHook();

        $clone = $repository->shallowClone('feature');

        return [$clone->runGuardrails(), $clone->runGuardrailsOn(['CI=true'])];
    });

    $line = "Guardrails checked nothing: origin/HEAD does not exist. Fetch the full history, as fetch-depth: 0 does for actions/checkout, then pass --base=origin/<branch>, or run git remote set-head origin --auto.\n";

    expect($default->exitCode)->toBe(0)
        ->and($default->output)->toBe($line)
        ->and($ci->exitCode)->toBe(2)
        ->and($ci->errorOutput)->toBe($line);
});
