<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('writes a header that explains the file above the checks of a preset', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $result = $repository->runGuardrails('install', '--preset=essentials');

        $source = (string) file_get_contents($repository->path.'/guardrails.php');

        expect($result->exitCode)->toBe(0)
            ->and($source)->toStartWith("<?php\n\n/*\n|----")
            ->and($source)->toContain('| Laravel Guardrails Checks')
            ->and($source)->toContain('vendor/bin/guardrails --base=origin/main')
            ->and($source)->toMatch('/laravel-guardrails\n\|\n\*\/\n\nuse /s')
            ->and(guardrails($repository->path))->toBe(builtIn('essentials'));
    });
});

it('leaves the file byte for byte as it is when the install runs again, and keeps one header when it adds to the selection', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->runGuardrails('install', '--preset=essentials')->throw();
        $first = file_get_contents($repository->path.'/guardrails.php');

        $repository->runGuardrails('install', '--preset=essentials')->throw();
        $second = file_get_contents($repository->path.'/guardrails.php');

        $repository->runGuardrails('install', '--preset=opinionated')->throw();
        $third = (string) file_get_contents($repository->path.'/guardrails.php');

        expect($second)->toBe($first)
            ->and(substr_count($third, '| Laravel Guardrails Checks'))->toBe(1);
    });
});

it('does not give a header to a guardrails.php the project already has', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $repository
            ->write('guardrails.php', "<?php\n\nreturn [\n    // The docs are prose.\n    \\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes::class,\n];\n")
            ->runGuardrails('install', '--preset=essentials')
            ->throw();

        expect(file_get_contents($repository->path.'/guardrails.php'))->toStartWith("<?php\n\nuse ")
            ->and(file_get_contents($repository->path.'/guardrails.php'))->not->toContain('| Laravel Guardrails Checks');
    });
});
