<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('judges the files of a base whose config does not parse as added, so the change that repairs it passes on its own merits', function (string $file, string $broken, string $repaired) {
    $result = ScratchRepository::using(function (ScratchRepository $repository) use ($file, $broken, $repaired) {
        $repository
            ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval'])
            ->write($file, $broken)
            ->write('app/Clean.php', "<?php\n\nclass Clean {}\n")
            ->write('app/Old.php', "<?php\n\neval('1');\n\nclass Old {}\n")
            ->stageAll()
            ->commitWithoutHook()
            ->branch('base');

        return $repository
            ->write($file, $repaired)
            ->write('app/Clean.php', "<?php\n\nclass Clean\n{\n}\n")
            ->write('app/Old.php', "<?php\n\neval('1');\n\nclass Old\n{\n}\n")
            ->stageAll()
            ->commitWithoutHook()
            ->runGuardrails('--base=base');
    });

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEval
          app/Old.php:3 Replace eval() with code that does the same, because it runs whatever string it is given.

        TEXT);
})->with([
    'composer.json' => ['composer.json', '{"name": "acme/app"', '{"name": "acme/app"}'],
    'phpunit.xml' => ['phpunit.xml', '<phpunit></testsuites>', '<phpunit/>'],
]);

it('stops with exit code 2 and names the commit whose composer.json is not valid JSON when the change leaves it so', function () {
    [$head, $result] = ScratchRepository::using(function (ScratchRepository $repository) {
        $repository
            ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval'])
            ->write('composer.json', '{"name": "acme/app"}')
            ->write('app/Ok.php', "<?php\n\nclass Ok {}\n")
            ->stageAll()
            ->commitWithoutHook()
            ->branch('base');

        $result = $repository
            ->write('composer.json', '{"name": "acme/app"')
            ->write('app/Ok.php', "<?php\n\nclass Ok\n{\n}\n")
            ->stageAll()
            ->commitWithoutHook()
            ->write('composer.json', '{"name": "acme/app"}')
            ->runGuardrails('--base=base');

        return [rtrim($repository->git('rev-parse', '--short', 'HEAD')), $result];
    });

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe("composer.json at {$head} is not valid JSON: Syntax error\n");
});

it('stops with exit code 2 and names the staged phpunit.xml that is not valid XML', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Tests\\NoDocblocksInTests'])
        ->write('phpunit.xml', '<phpunit></testsuites>')
        ->write('tests/Unit/ExampleTest.php', "<?php\n\nit('works', fn () => true);\n")
        ->stageAll()
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe("the staged phpunit.xml is not valid XML: Opening and ending tag mismatch: phpunit line 1 and testsuites (line 1).\n");
});

it('names where it crashed and exits with code 2 when a check throws before reading any file', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', <<<'PHP'
            <?php

            use ClaudioDekker\LaravelGuardrails\Check;
            use ClaudioDekker\LaravelGuardrails\File;

            return [
                (new class extends Check
                {
                    public function description(): string
                    {
                        return 'Reads the files it has no patterns for yet.';
                    }

                    protected function files(): array
                    {
                        throw new LogicException('The patterns are not decided yet.');
                    }

                    protected function violations(File $file): array
                    {
                        return [];
                    }
                })->as('Undecided'),
            ];
            PHP)
        ->write('README.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe("Guardrails crashed at guardrails.php:16: The patterns are not decided yet.\n");
});

it('stops with exit code 2 when PHP runs out of memory', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', <<<'PHP'
        <?php

        use ClaudioDekker\LaravelGuardrails\Check;
        use ClaudioDekker\LaravelGuardrails\File;
        use ClaudioDekker\LaravelGuardrails\Violation;

        return [
            (new class extends Check
            {
                public function description(): string
                {
                    return 'Holds more than the memory limit allows.';
                }

                protected function files(): array
                {
                    return ['*'];
                }

                protected function violations(File $file): array
                {
                    return [Violation::file(str_repeat('x', 1024 * 1024 * 1024))];
                }
            })->as('Hungry'),
        ];
        PHP)
        ->write('README.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrailsWithMemoryLimit('64M', '--all'));

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toContain('Guardrails stopped: Allowed memory size of 67108864 bytes exhausted');
});
