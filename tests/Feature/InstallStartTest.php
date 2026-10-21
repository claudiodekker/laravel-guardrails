<?php

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

it('starts on the app preset for a project, and on the package preset for a library or a composer.json without a type', function (array $manifest, string $preset) {
    $directory = project($manifest);

    $exitCode = install($directory, [Key::ENTER, Key::ENTER, Key::ENTER]);

    expect($exitCode)->toBe(0)
        ->and(guardrails($directory))->toBe(builtIn($preset));
})->with([
    'a project' => [['name' => 'acme/app', 'type' => 'project'], 'app'],
    'a library' => [['name' => 'acme/lib', 'type' => 'library'], 'package'],
    'no type' => [['name' => 'acme/lib'], 'package'],
    'no composer.json' => [[], 'package'],
]);

it('lists the presets in catalog order whatever it starts on', function () {
    $directory = project(['name' => 'acme/app', 'type' => 'project']);

    install($directory, [Key::ENTER, Key::ENTER, Key::ENTER]);

    expect(anyFrame(fn (string $frame) => preg_match('/essentials.*app.*package.*laravel.*opinionated/s', $frame) === 1))->toBeTrue();
});

it('starts on the current selection of a project, not on the app preset', function () {
    $directory = projectSelecting("<?php\n\nreturn [\\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes::class];\n", ['name' => 'acme/app', 'type' => 'project']);
    $before = file_get_contents($directory.'/guardrails.php');

    $exitCode = install($directory, [Key::ENTER, Key::ENTER]);

    expect($exitCode)->toBe(0)
        ->and(file_get_contents($directory.'/guardrails.php'))->toBe($before);

    Prompt::assertStrippedOutputContains('The selection is unchanged.');
});

it('starts from the union of the presets ticked in the start step', function () {
    $directory = project(['name' => 'acme/app', 'type' => 'project']);

    $exitCode = install($directory, [Key::CTRL_A, Key::CTRL_A, Key::SPACE, Key::DOWN, Key::DOWN, Key::DOWN, Key::SPACE, Key::ENTER, Key::ENTER, Key::ENTER]);

    expect($exitCode)->toBe(0)
        ->and(guardrails($directory))->toBe(array_values(array_intersect(builtIn('opinionated'), [...builtIn('essentials'), ...builtIn('laravel')])));
});

it('starts from nothing when no preset is ticked in the start step', function () {
    $directory = project(['name' => 'acme/app']);

    install($directory, [Key::CTRL_A, Key::CTRL_A, Key::ENTER, Key::ENTER]);

    expect(file_exists($directory.'/guardrails.php'))->toBeFalse();

    Prompt::assertStrippedOutputContains('The selection is unchanged.');
});

it('adds the current selection to the presets ticked in the start step', function () {
    $directory = projectSelecting("<?php\n\nreturn [\\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\FilesWithinTokenBudget::class];\n", ['name' => 'acme/app']);

    install($directory, [Key::DOWN, Key::SPACE, Key::ENTER, Key::ENTER, Key::ENTER]);

    expect(guardrails($directory))->toBe(array_values(array_unique([...['ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget'], ...builtIn('essentials')])));
});
