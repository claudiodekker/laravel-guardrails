<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoUnserialize;
use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('lists the built-in checks by group with the ones guardrails.php selects marked, then the presets with their checks', function () {
    $directory = projectSelecting("<?php\n\nreturn [\\ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval::class];\n");

    $result = listChecks($directory);

    expect($result['exitCode'])->toBe(0)
        ->and($result['errorOutput'])->toBe('')
        ->and($result['output'])->toStartWith("claudiodekker/laravel-guardrails\n  Text\n    [ ] FilesWithinTokenBudget")
        ->and($result['output'])->toMatch('/^  Php › Safety\n(.*\n)*?    \[x\] NoEval +PHP +No eval\(\) anywhere\.\n/m')
        ->and($result['output'])->toMatch('/^    \[ \] NoEmDashes +every file +No em dash in any file\.$/m')
        ->and($result['output'])->toMatch('/^    \[ \] TestsInTestSuites +\*Test\.php in tests and other files, outside Fixtures folders +Every test sits in a folder/m')
        ->and($result['output'])->toContain("\nPresets\n  essentials (claudiodekker/laravel-guardrails)\n    The checks that catch mistakes in any Laravel project, whatever its style.\n    NoFunctionsInTests, TestsInTestSuites, OneClassPerFile, ")
        ->and($result['output'])->toContain("\n  opinionated (claudiodekker/laravel-guardrails)\n    Every check, for a project that wants the whole rulebook.\n    FilesWithinTokenBudget, NoEmDashes, NoDocblocksInTests, ")
        ->and($result['output'])->toEndWith("\n");
});

it('renders the row and the details of a configured check from its instance', function () {
    $directory = projectSelecting("<?php\n\nreturn [new \\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\FilesWithinTokenBudget(limit: 8000)];\n");

    $result = listChecks($directory, ['--details']);

    expect($result['output'])->toMatch('/^    \[x\] FilesWithinTokenBudget +every file but data files +Every file stays under 8000 tokens, so an agent can read it whole\.$/m')
        ->and($result['output'])->toContain('This flags any file over 32000 bytes, counted as 8000 tokens')
        ->and($result['output'])->toMatch('/^    \[ \] NoEmDashes /m');
});

it('lists the checks of an installed pack and of the project under their package, grouped below the namespace they share, and names the ones that are not offered', function () {
    $directory = project(
        ['name' => 'acme/app', 'extra' => ['guardrails' => ['provides' => ['checks' => ['ClaudioDekker\LaravelGuardrails\Tests\Support\NoEmDashes']]]]],
        [installedPack('acme/pack', ['ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine', 'ClaudioDekker\LaravelGuardrails\Tests\Support\Nested\EveryLine', 'Acme\Pack\Missing'], ['safe' => ['ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine']])],
    );

    $result = listChecks($directory);

    expect($result['output'])->toContain(<<<'TEXT'

        acme/pack
            [ ] EveryLine  every file  Every line breaks the rule.
          Nested
            [ ] EveryLine  every file  Every line breaks the rule.
            Acme\Pack\Missing does not exist or is not a check, so it is not offered.

        acme/app
            [ ] NoEmDashes  every file  Every line breaks the rule.

        Presets
        TEXT)
        ->and($result['output'])->toContain("\n  safe (acme/pack)\n    EveryLine\n");
});

it('prints the explanation of each check under its row with --details, wrapped at 80 columns', function () {
    $directory = project(
        ['name' => 'acme/app', 'extra' => ['guardrails' => ['provides' => ['checks' => ['ClaudioDekker\LaravelGuardrails\Tests\Support\Explained']]]]],
        [installedPack('acme/pack', ['ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine'])],
    );

    $result = listChecks($directory, ['--details']);

    expect($result['exitCode'])->toBe(0)
        ->and(max([0, ...array_map(mb_strwidth(...), array_filter(explode("\n", $result['output']), fn (string $line) => str_starts_with($line, '        ')))]))->toBeLessThanOrEqual(80)
        ->and(preg_replace('/\n {8}/', ' ', $result['output']))->toMatch('/^    \[ \] NoUnserialize .+ '.preg_quote((new NoUnserialize)->explanation(), '/').'$/m')
        ->and($result['output'])->toContain(<<<'TEXT'

        acme/pack
            [ ] EveryLine  every file  Every line breaks the rule.
                Every line breaks the rule.

        acme/app
            [ ] Explained  every file  Every line breaks the rule.
                Every line breaks this rule, so the check flags each line of every file
                it reads. A rule this strict makes no sense in a real project, but it
                shows how a long explanation wraps. The paragraph wraps to the width of
                the terminal and keeps its words whole. On a terminal too short for all
                of it, the last line it has room for ends in an ellipsis.

        TEXT);
});

it('leaves out the packs the project does not discover', function () {
    $directory = project(['name' => 'acme/app', 'extra' => ['guardrails' => ['dont-discover' => ['*']]]], [installedPack('acme/pack', ['ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine'])]);

    $result = listChecks($directory);

    expect($result['output'])->toBe('')
        ->and($result['exitCode'])->toBe(0);
});

it('lists without a terminal', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository->selectPreset('essentials')->runGuardrails('list'));

    expect($result->exitCode)->toBe(0)
        ->and($result->output)->toStartWith("claudiodekker/laravel-guardrails\n")
        ->and($result->output)->toMatch('/^    \[x\] NoEval /m')
        ->and($result->output)->toMatch('/^    \[ \] NoEmDashes /m');
});

it('refuses an argument after list and a broken composer.json with exit code 2', function (array $manifest, array $arguments, int $exitCode, string $message) {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('composer.json', $manifest === [] ? '{' : json_encode($manifest, JSON_THROW_ON_ERROR))
        ->runGuardrails('list', ...$arguments));

    expect($result->exitCode)->toBe($exitCode)
        ->and($result->errorOutput)->toContain($message);
})->with([
    'an argument' => [['name' => 'acme/app'], ['--all'], 2, 'Unknown argument --all. Usage: guardrails list [--details] [--help]'],
    'a broken composer.json' => [[], [], 2, 'is not valid JSON'],
]);

it('prints the message of an error a check raises while it is listed, and exits with code 2', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', "<?php\n\nreturn [\n    \\ClaudioDekker\\LaravelGuardrails\\Tests\\Support\\Unavailable::class,\n];\n")
        ->runGuardrails('list'));

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe("The licence server is down.\n");
});

it('renders the paths a configured check is limited to after the files it reads', function () {
    $directory = projectSelecting("<?php\n\nreturn [(new \\ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval)->only('app', 'packages/*/src')];\n");

    $result = listChecks($directory);

    expect($result['output'])->toMatch('/^    \[x\] NoEval +PHP, under app, packages\/\*\/src +No eval\(\) anywhere\.$/m')
        ->and($result['output'])->toMatch('/^    \[ \] NoEmDashes +every file +No em dash in any file\.$/m');
});

it('keeps a blank line between the groups of a pack with --details', function () {
    $directory = projectSelecting("<?php\n\nreturn [];\n");

    $result = listChecks($directory, ['--details']);

    expect($result['output'])->toMatch('/^        [^\n]*\n\n  Php › Tests\n/m')
        ->and($result['output'])->not->toMatch('/^        [^\n]*\n  Php › /m');
});

it('lists each configured instance of a check as a row of its own', function () {
    $directory = projectSelecting("<?php\n\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\FilesWithinTokenBudget;\n\nreturn [\n    (new FilesWithinTokenBudget(limit: 8000))->only('docs'),\n    (new FilesWithinTokenBudget(limit: 3000))->only('app'),\n];\n");

    $result = listChecks($directory);

    expect($result['output'])->toMatch('/^  Text\n    \[x\] FilesWithinTokenBudget +every file but data files, under docs +Every file stays under 8000 tokens, so an agent can read it whole\.\n    \[x\] FilesWithinTokenBudget +every file but data files, under app +Every file stays under 3000 tokens, so an agent can read it whole\.\n    \[ \] NoEmDashes /m');
});

it('lists the checks guardrails.php selects that no package offers under its name, after the packs', function () {
    $directory = projectSelecting(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\NoNamespaces;
    use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;

    return [
        (new NoNamespaces(['Acme\Billing']))->only('packages/crm/src'),
        EveryLine::class,
        (new NoNamespaces(['Acme\Crm', 'Acme\Shop']))->only('packages/billing/src'),
    ];

    PHP);

    $result = listChecks($directory, ['--details']);

    expect($result['exitCode'])->toBe(0)
        ->and($result['output'])->toContain(<<<'TEXT'

        guardrails.php
            [x] NoNamespaces  PHP, under packages/crm/src      No name from Acme\Billing.
                Some code may not depend on another part of the project, such as a
        TEXT)
        ->and($result['output'])->toContain(<<<'TEXT'

            [x] EveryLine     every file                       Every line breaks the rule.
                Every line breaks the rule.

            [x] NoNamespaces  PHP, under packages/billing/src  No name from Acme\Crm or Acme\Shop.
        TEXT)
        ->and($result['output'])->toMatch('/\n\nPresets\n/');
});

it('refuses a guardrails.php the run refuses, with the same message', function () {
    $directory = projectSelecting("<?php\n\nreturn [\\ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Structure\\NoNamespaces::class];\n");

    $result = listChecks($directory);

    expect($result['exitCode'])->toBe(2)
        ->and($result['output'])->toBe('')
        ->and($result['errorOutput'])->toBe("ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Structure\\NoNamespaces takes options. Write it as new NoNamespaces(...) in guardrails.php.\n");
});

it('refuses an anonymous check that as() does not name, as the run does', function () {
    $directory = projectSelecting("<?php\n\nreturn [\n    new class extends \\ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval {},\n];\n");

    $result = listChecks($directory);

    expect($result['exitCode'])->toBe(2)
        ->and($result['output'])->toBe('')
        ->and($result['errorOutput'])->toBe("{$directory}/guardrails.php: entry 1 is an anonymous class. Name it with ->as('...').\n");
});
