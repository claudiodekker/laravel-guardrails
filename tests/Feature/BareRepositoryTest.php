<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('passes an empty repository', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository->select()->runGuardrails('--all'));

    expect($result->successful())->toBeTrue()
        ->and($result->output)->toBe("Guardrails passed: 7 checks over 0 files (every tracked file).\n");
});

it('passes a repository holding only files no check reads', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('.gitignore', "/vendor\n")
        ->write('public/favicon.ico', "\0\0\1\0")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->successful())->toBeTrue()
        ->and($result->output)->toBe("Guardrails passed: 7 checks over 2 files (every tracked file).\n");
});

it('passes a bare Laravel skeleton with every tracked file under every built-in check', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->copy(__DIR__.'/../Fixtures/skeleton')
        ->selectPreset('opinionated')
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->errorOutput)->toBe('')
        ->and($result->successful())->toBeTrue()
        ->and($result->output)->toBe("Guardrails passed: 35 checks over 64 files (every tracked file).\n");
});

it('passes a bare Laravel skeleton when it is staged', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->copy(__DIR__.'/../Fixtures/skeleton')
        ->selectPreset('opinionated')
        ->stageAll()
        ->runGuardrails('--staged'));

    expect($result->errorOutput)->toBe('')
        ->and($result->successful())->toBeTrue()
        ->and($result->output)->toBe("Guardrails passed: 35 checks over 64 files (staged changes).\n");
});
