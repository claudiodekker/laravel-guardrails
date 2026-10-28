<?php

use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;
use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;
use ClaudioDekker\LaravelGuardrails\Tests\Support\Explained;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

it('opens a group, jumps from a check back to its group, and closes it', function (string $open, string $down, string $close) {
    $directory = project(['name' => 'acme/app']);

    install($directory, [
        Key::CTRL_A, Key::CTRL_A, Key::ENTER,
        $open, $down, $down, $close, $close, $down, Key::SPACE, Key::ENTER,
        Key::ENTER,
    ]);

    expect(guardrails($directory))->toBe([
        'ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\NoDocblocksInTests',
        'ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\NoFunctionsInTests',
        'ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\NoHttpInUnitTests',
        'ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\TestsInTestSuites',
        'ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\TestsInUnitOrFeature',
    ]);

    Prompt::assertStrippedOutputContains('Space ticks a preset.');
    Prompt::assertStrippedOutputContains('› ▾ ◻ Text             0 of 2 on');
    Prompt::assertStrippedOutputContains('›     ◻ NoEmDashes');
    Prompt::assertStrippedOutputContains('NoEmDashes  every file');
    Prompt::assertStrippedOutputContains('No em dash in any file.');
    Prompt::assertStrippedOutputContains('› ▸ ◼ Php › Tests      5 of 5 on');
})->with([
    'arrows' => [Key::RIGHT, Key::DOWN, Key::LEFT],
    'vim keys' => ['l', 'j', 'h'],
]);

it('turns a check on and writes it, in catalog order', function () {
    $directory = project(['name' => 'acme/app']);

    install($directory, [
        Key::CTRL_A, Key::CTRL_A, Key::ENTER,
        Key::RIGHT, Key::DOWN, Key::DOWN, Key::SPACE, Key::UP, Key::SPACE, Key::ENTER,
        Key::ENTER,
    ]);

    expect(guardrails($directory))->toBe([
        'ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget',
        'ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes',
    ]);

    Prompt::assertStrippedOutputContains('›     ◼ NoEmDashes');
    Prompt::assertStrippedOutputContains('  ▾ ▣ Text             1 of 2 on');
    Prompt::assertStrippedOutputContains('  ▾ ◼ Text             2 of 2 on');
    Prompt::assertStrippedOutputContains('FilesWithinTokenBudget  every file');
    Prompt::assertStrippedOutputContains('Every file stays under 5000 tokens, so an agent can read it whole.');
});

it('turns a whole group on and off with space, flipping each check it shows', function () {
    $directory = project(['name' => 'acme/app']);

    install($directory, [
        Key::CTRL_A, Key::CTRL_A, Key::SPACE, Key::ENTER,
        Key::DOWN, Key::DOWN, Key::DOWN, Key::DOWN, Key::RIGHT, Key::SPACE, Key::SPACE, Key::ENTER,
        Key::ENTER,
    ], lines: 30);

    expect(guardrails($directory))->toBe(array_values(array_diff(builtIn('essentials'), [
        'ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls',
        'ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval',
        'ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoUnserialize',
    ])));

    Prompt::assertStrippedOutputContains('Php › Safety: 3 of 5 on. Space turns them all on.');
    Prompt::assertStrippedOutputContains('› ▾ ◼ Php › Safety     5 of 5 on');
    Prompt::assertStrippedOutputContains('      ◼ NoWeakHashes');
    Prompt::assertStrippedOutputContains('Php › Safety: 5 of 5 on. Space turns them all off.');
    Prompt::assertStrippedOutputContains('› ▾ ◻ Php › Safety     0 of 5 on');
    Prompt::assertStrippedOutputContains('      ◻ NoDebugCalls');
    Prompt::assertStrippedOutputContains('Turned on:');
    Prompt::assertStrippedOutputContains('  ClaudioDekker\LaravelGuardrails\Checks\Php\Config\NoEnvOutsideConfig');
    Prompt::assertStrippedOutputDoesntContain('  ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval');
    Prompt::assertStrippedOutputDoesntContain('Turned off:');
});

it('adds the checks of a preset chosen with p to the tree, and keeps the tree when the choice is escaped', function () {
    $directory = project(['name' => 'acme/app']);

    install($directory, [
        Key::CTRL_A, Key::CTRL_A, Key::ENTER,
        'p', Key::DOWN, Key::ESCAPE, 'p', Key::ENTER, 'p', Key::DOWN, Key::DOWN, Key::DOWN, Key::ENTER, Key::ENTER,
        Key::ENTER,
    ]);

    expect(guardrails($directory))->toBe(array_values(array_intersect(builtIn('opinionated'), [...builtIn('essentials'), ...builtIn('laravel')])));

    Prompt::assertStrippedOutputContains('› ● essentials (claudiodekker/laravel-guardrails), 9 checks');
    Prompt::assertStrippedOutputContains('Enter adds the checks of the preset, esc keeps the tree as it is.');
    expect(anyFrame(fn (string $frame) => str_contains($frame, 'Php › Safety') && str_contains($frame, '0 of 39 on')))->toBeTrue()
        ->and(anyFrame(fn (string $frame) => str_contains($frame, 'Php › Safety') && str_contains($frame, '9 of 39 on')))->toBeTrue();
});

it('scrolls the tree when the open groups do not fit', function () {
    $directory = project(['name' => 'acme/app']);

    $exitCode = install($directory, [
        Key::CTRL_A, Key::CTRL_A, Key::ENTER,
        Key::DOWN, Key::DOWN, Key::DOWN, Key::DOWN, Key::RIGHT, "\e[F", Key::ENTER,
    ]);

    expect($exitCode)->toBe(0)
        ->and(anyFrame(fn (string $frame) => str_contains($frame, '› ▸ ◻ Php › Package') && ! str_contains($frame, 'Text ')))->toBeTrue();

    Prompt::assertStrippedOutputContains('┃');
    Prompt::assertStrippedOutputContains('The selection is unchanged.');
});

it('keeps every line within 80 columns', function () {
    $directory = project(['name' => 'acme/app']);

    install($directory, [
        Key::CTRL_A, Key::CTRL_A, Key::ENTER,
        Key::DOWN, Key::DOWN, Key::DOWN, Key::DOWN, Key::RIGHT, Key::DOWN, Key::SPACE, Key::ENTER,
        Key::ENTER,
    ]);

    $widths = array_map(mb_strwidth(...), explode("\n", Prompt::strippedContent()));

    expect(max($widths))->toBeLessThanOrEqual(80);

    Prompt::assertStrippedOutputContains('NoDebugCalls  PHP outside tests');
    Prompt::assertStrippedOutputContains('No dd(), dump(), ray(), var_dump() or print_r() outside tests.');
});

it('explains the focused check below the keys, and explains the next one when the focus moves', function () {
    $directory = project(['name' => 'acme/app']);

    install($directory, [
        Key::CTRL_A, Key::CTRL_A, Key::ENTER,
        Key::RIGHT, Key::DOWN, Key::DOWN, Key::ENTER,
    ]);

    $budget = (new FilesWithinTokenBudget)->explanation();
    $dashes = (new NoEmDashes)->explanation();
    $explains = fn (string $frame, string $explanation) => str_contains((string) preg_replace('/\s+/', ' ', $frame), $explanation);

    $focused = fn (string $name, string $explanation, string $other) => anyFrame(fn (string $frame) => str_contains($frame, "›     ◻ {$name}")
        && $explains($frame, $explanation)
        && ! $explains($frame, $other));

    expect($focused('FilesWithinTokenBudget', $budget, $dashes))->toBeTrue()
        ->and($focused('NoEmDashes', $dashes, $budget))->toBeTrue();
});

it('lays out the focused row under the tree, with its explanation between the description and the keys', function () {
    $directory = project(['name' => 'acme/app'], [installedPack('acme/pack', [EveryLine::class, Explained::class])]);

    install($directory, [
        Key::CTRL_A, Key::CTRL_A, Key::ENTER,
        "\e[F", Key::RIGHT, Key::DOWN, Key::DOWN, Key::ENTER,
    ]);

    Prompt::assertStrippedOutputContains(<<<'TEXT'
        ┘

          acme/pack: 0 of 2 on. Space turns them all on.

          The checks acme/pack provides.

          ↑ ↓ move  ← → open or close  space toggle  p preset  enter finish
        TEXT);

    Prompt::assertStrippedOutputContains(<<<'TEXT'
        ┘

          EveryLine  every file
          Every line breaks the rule.

          Every line breaks the rule.

          ↑ ↓ move  ← → open or close  space toggle  p preset  enter finish
        TEXT);

    $frame = array_values(array_filter(treeFrames(), fn (string $frame) => str_contains($frame, '›     ◻ Explained')))[0];
    $paragraph = array_slice(explode("\n", explode("\n\n", explode('Every line breaks the rule.', $frame, 2)[1], 3)[1]), 0);

    expect(count($paragraph))->toBeGreaterThan(2)
        ->and(max(array_map(mb_strwidth(...), explode("\n", $frame))))->toBeLessThanOrEqual(80)
        ->and(implode(' ', array_map(trim(...), $paragraph)))->toBe((new Explained)->explanation());
});

it('shrinks the tree and then truncates the explanation so the prompt fits a short terminal', function () {
    $directory = project(['name' => 'acme/app'], [installedPack('acme/pack', [EveryLine::class, Explained::class])]);

    install($directory, [
        Key::CTRL_A, Key::CTRL_A, Key::ENTER,
        "\e[F", Key::RIGHT, Key::DOWN, Key::DOWN, Key::ENTER,
    ], lines: 16);

    $frames = treeFrames();
    $explained = array_values(array_filter($frames, fn (string $frame) => str_contains($frame, '›     ◻ Explained')))[0];

    expect($frames)->not->toBe([])
        ->and(max([0, ...array_map(fn (string $frame) => count(explode("\n", $frame)), $frames)]))->toBeLessThanOrEqual(16)
        ->and($explained)->toContain('Every line breaks this rule, so the check flags each line')
        ->and($explained)->toContain('…')
        ->and($explained)->not->toContain('ends in an ellipsis.')
        ->and($explained)->toContain('enter finish');
});

it('marks a configured check and describes it as configured', function () {
    $directory = projectSelecting(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

    return [
        new FilesWithinTokenBudget(limit: 8000),
        NoEmDashes::class,
    ];

    PHP);

    install($directory, [Key::ENTER, Key::RIGHT, Key::DOWN, Key::ENTER]);

    Prompt::assertStrippedOutputContains('› ▾ ◼ Text             2 of 2 on');
    Prompt::assertStrippedOutputContains('›     ◼ FilesWithinTokenBudget  configured');
    Prompt::assertStrippedOutputContains('      ◼ NoEmDashes');
    Prompt::assertStrippedOutputContains('Every file stays under 8000 tokens, so an agent can read it whole.');
    Prompt::assertStrippedOutputContains('The selection is unchanged.');
});

it('marks a check configured through calls chained on its instance', function () {
    $directory = projectSelecting(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

    return [
        (new FilesWithinTokenBudget(limit: 8000))->only('app', 'packages/*/src'),
        NoEmDashes::class,
    ];

    PHP);

    $before = file_get_contents($directory.'/guardrails.php');

    install($directory, [Key::ENTER, Key::RIGHT, Key::DOWN, Key::ENTER]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe($before);

    Prompt::assertStrippedOutputContains('› ▾ ◼ Text             2 of 2 on');
    Prompt::assertStrippedOutputContains('›     ◼ FilesWithinTokenBudget  configured');
    Prompt::assertStrippedOutputContains('FilesWithinTokenBudget  every file but data files, under app');
    Prompt::assertStrippedOutputContains('Every file stays under 8000 tokens, so an agent can read it whole.');
    Prompt::assertStrippedOutputContains('The selection is unchanged.');
});
