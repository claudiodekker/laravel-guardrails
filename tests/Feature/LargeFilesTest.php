<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('passes binary files larger than the memory limit, reading no more than the start of each', function (array $arguments, string $output) {
    $video = str_repeat("\0".random_bytes(1023), 24 * 1024);

    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->write('public/intro.mp4', $video)
        ->write('public/outro.mp4', strrev($video))
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrailsWithMemoryLimit('16M', ...$arguments));

    expect($result->errorOutput)->toBe('')
        ->and($result->output)->toBe($output);
})->with([
    'against the default branch' => [[], "Guardrails passed: 7 checks over 0 files (origin/HEAD..HEAD).\n"],
    'over every tracked file' => [['--all'], "Guardrails passed: 7 checks over 2 files (every tracked file).\n"],
]);

it('never reads a lockfile or a vendor file, however large', function (array $arguments, string $output) {
    $text = str_repeat("Not read.\n", 2500 * 1000);

    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->write('package-lock.json', $text)
        ->write('vendor/acme/player/dist/player.js', $text)
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrailsWithMemoryLimit('16M', ...$arguments));

    expect($result->errorOutput)->toBe('')
        ->and($result->output)->toBe($output);
})->with([
    'against the default branch' => [[], "Guardrails passed: 7 checks over 0 files (origin/HEAD..HEAD).\n"],
    'over every tracked file' => [['--all'], "Guardrails passed: 7 checks over 2 files (every tracked file).\n"],
]);

it('keeps the patch of an edited lockfile or vendor file out of memory, however large', function () {
    $before = str_repeat("Not read.\n", 1000 * 1000);
    $after = str_repeat("Still not read.\n", 1000 * 1000);

    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Clean.\n")
        ->write('composer.lock', $before)
        ->write('vendor/acme/player/dist/player.js', $before)
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->write('composer.lock', $after)
        ->write('vendor/acme/player/dist/player.js', $after)
        ->write('README.md', "Still clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrailsWithMemoryLimit('16M'));

    expect($result->errorOutput)->toBe('')
        ->and($result->output)->toBe("Guardrails passed: 7 checks over 1 file (origin/HEAD..HEAD).\n");
});
