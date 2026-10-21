<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\NoNamespaces;
use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;
use ClaudioDekker\LaravelGuardrails\Tests\Support\OnlyLine;
use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

it('keeps an entry of a check no package offers as it is written when the flags select a preset', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $result = $repository
            ->write('guardrails.php', "<?php\n\nreturn [\n    // The docs are prose.\n    new \\ClaudioDekker\\LaravelGuardrails\\Tests\\Support\\EveryLine(null, ['*.md']),\n];\n")
            ->runGuardrails('install', '--preset=essentials');

        expect($result->exitCode)->toBe(0)
            ->and($result->errorOutput)->toBe('')
            ->and($result->output)->toContain("Selected 10 checks in guardrails.php.\n")
            ->and(guardrails($repository->path))->toBe([...builtIn('essentials'), 'ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine'])
            ->and(file_get_contents($repository->path.'/guardrails.php'))->toContain("    // The docs are prose.\n    new \\ClaudioDekker\\LaravelGuardrails\\Tests\\Support\\EveryLine(null, ['*.md']),\n");
    });
});

it('keeps every configured NoNamespaces entry as it is written, since no preset or flag can configure one', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $entries = "    (new NoNamespaces(['Acme\\Billing']))->only('packages/crm/src'),\n    (new NoNamespaces(['Acme\\Crm']))->only('packages/billing/src'),\n";

        $result = $repository
            ->write('guardrails.php', "<?php\n\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Structure\\NoNamespaces;\n\nreturn [\n{$entries}];\n")
            ->runGuardrails('install', '--preset=essentials');

        expect($result->exitCode)->toBe(0)
            ->and($result->errorOutput)->toBe('')
            ->and(guardrails($repository->path))->toBe([...builtIn('essentials'), NoNamespaces::class, NoNamespaces::class])
            ->and(file_get_contents($repository->path.'/guardrails.php'))->toContain($entries);
    });
});

it('keeps the entries of checks no package offers when the developer starts from a preset, and says so instead of listing them as turned off', function () {
    $directory = projectSelecting(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;
    use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;
    use ClaudioDekker\LaravelGuardrails\Tests\Support\OnlyLine;

    return [
        new EveryLine(null, ['*.md']),
        NoEmDashes::class,
        new OnlyLine(1),
    ];

    PHP);

    $exitCode = install($directory, [Key::SPACE, Key::DOWN, Key::SPACE, Key::ENTER, Key::ENTER, Key::ENTER]);

    expect($exitCode)->toBe(0)
        ->and(guardrails($directory))->toBe([...builtIn('essentials'), EveryLine::class, OnlyLine::class])
        ->and(file_get_contents($directory.'/guardrails.php'))->toContain("    new EveryLine(null, ['*.md']),\n")
        ->and(file_get_contents($directory.'/guardrails.php'))->toContain("    new OnlyLine(1),\n");

    Prompt::assertStrippedOutputContains('Turned on:');
    Prompt::assertStrippedOutputContains('Turned off:');
    Prompt::assertStrippedOutputContains('  ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes');
    Prompt::assertStrippedOutputContains('Kept as written: 2 entries no package offers.');
    Prompt::assertStrippedOutputDoesntContain('  ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine');
    Prompt::assertStrippedOutputContains('Selected 11 checks in guardrails.php.');
});

it('counts one kept entry in the singular', function () {
    $directory = projectSelecting("<?php\n\nreturn [\n    new \\ClaudioDekker\\LaravelGuardrails\\Tests\\Support\\OnlyLine(1),\n];\n");

    install($directory, [Key::SPACE, Key::DOWN, Key::SPACE, Key::ENTER, Key::ENTER, Key::ENTER]);

    expect(guardrails($directory))->toBe([...builtIn('essentials'), OnlyLine::class]);

    Prompt::assertStrippedOutputContains('Kept as written: 1 entry no package offers.');
});
