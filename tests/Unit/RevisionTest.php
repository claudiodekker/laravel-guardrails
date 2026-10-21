<?php

use ClaudioDekker\LaravelGuardrails\Git;
use ClaudioDekker\LaravelGuardrails\Projects\Revision;
use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('resolves the short hash of a commit once, however many files it describes', function () {
    [$descriptions, $resolved, $commit] = ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->write('README.md', "One.\n")->stageAll()->commitWithoutHook();

        $git = new class($repository->path) extends Git
        {
            public int $resolved = 0;

            public function run(string ...$arguments): string
            {
                if (in_array('--short', $arguments, true)) {
                    $this->resolved++;
                }

                return parent::run(...$arguments);
            }
        };

        $revision = new Revision($git, 'HEAD');

        return [[$revision->describe('a.php'), $revision->describe('b.php')], $git->resolved, rtrim($repository->git('rev-parse', '--short', 'HEAD'))];
    });

    expect($descriptions)->toBe(["a.php at {$commit}", "b.php at {$commit}"])
        ->and($resolved)->toBe(1);
});
