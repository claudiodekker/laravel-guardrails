<?php

use ClaudioDekker\LaravelGuardrails\Blobs;
use ClaudioDekker\LaravelGuardrails\Git;
use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('reads each blob by its id in any order, and the start of a long one alone', function () {
    $contents = str_repeat("0123456789\n", 1000);

    $read = ScratchRepository::using(function (ScratchRepository $repository) use ($contents) {
        $repository->write('long.txt', $contents)->write('empty.txt', '')->write('short.txt', "Short.\n")->stageAll();

        [$long, $empty, $short] = array_map(fn (string $path) => rtrim($repository->git('rev-parse', ":{$path}")), ['long.txt', 'empty.txt', 'short.txt']);
        $blobs = Blobs::of(new Git($repository->path), [$long, $empty, $short, $long]);

        return [$blobs->read($short), $blobs->head($long), $blobs->read($empty), $blobs->read($long)];
    });

    expect($read)->toBe(["Short.\n", substr($contents, 0, 8000), '', $contents]);
});

it('refuses a blob git does not have', function () {
    $blob = str_repeat('1', 40);

    expect(fn () => ScratchRepository::using(fn (ScratchRepository $repository) => Blobs::of(new Git($repository->path), [$blob])))
        ->toThrow(RuntimeException::class, "git has no blob {$blob}.");
});
