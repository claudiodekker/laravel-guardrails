<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

it('writes the preset the developer picks', function () {
    $directory = project(['name' => 'acme/app']);

    $exitCode = install($directory, [Key::CTRL_A, Key::CTRL_A, Key::SPACE, Key::ENTER, Key::ENTER, Key::ENTER]);

    expect($exitCode)->toBe(0)
        ->and(guardrails($directory))->toBe(builtIn('essentials'));

    Prompt::assertStrippedOutputContains('essentials (claudiodekker/laravel-guardrails), 9 checks');
    Prompt::assertStrippedOutputContains('› ▸ ◻ Text             0 of 2 on');
    Prompt::assertStrippedOutputContains('  ▸ ▣ Php › Safety     3 of 5 on');
    Prompt::assertStrippedOutputContains('9 of 34 on');
    Prompt::assertStrippedOutputContains('Turned on:');
    Prompt::assertStrippedOutputContains('Write the selection to guardrails.php?');
    Prompt::assertStrippedOutputContains('Selected 9 checks in guardrails.php.');
    Prompt::assertStrippedOutputContains('Not inside a git repository, so no pre-commit hook was installed.');
});

it('starts from the current selection on a rerun, and leaves guardrails.php alone when nothing changed', function () {
    $directory = projectSelecting("<?php\n\nreturn [\\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes::class];\n");
    $before = file_get_contents($directory.'/guardrails.php');

    $exitCode = install($directory, [Key::ENTER, Key::RIGHT, Key::LEFT, Key::ENTER]);

    expect($exitCode)->toBe(0)
        ->and(file_get_contents($directory.'/guardrails.php'))->toBe($before);

    Prompt::assertStrippedOutputContains('Your current selection, 1 checks');
    Prompt::assertStrippedOutputContains('› ▾ ▣ Text             1 of 2 on');
    Prompt::assertStrippedOutputContains('The selection is unchanged.');
    Prompt::assertStrippedOutputContains('Not inside a git repository, so no pre-commit hook was installed.');
    Prompt::assertStrippedOutputDoesntContain('Write the selection to guardrails.php?');
});

it('still installs the hook when the selection is unchanged', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->select(['ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes']);
        $before = file_get_contents($repository->path.'/guardrails.php');

        $exitCode = install($repository->path, [Key::ENTER, Key::ENTER]);

        expect($exitCode)->toBe(0)
            ->and(file_get_contents($repository->path.'/guardrails.php'))->toBe($before)
            ->and(is_executable($repository->hookPath()))->toBeTrue();

        Prompt::assertStrippedOutputContains("Installed the pre-commit hook at {$repository->hookPath()}.");
    });
});

it('shows what the selection turns on and off, and leaves guardrails.php alone when the developer does not confirm', function () {
    $directory = projectSelecting("<?php\n\nreturn [\\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes::class];\n");
    $before = file_get_contents($directory.'/guardrails.php');

    install($directory, [
        Key::ENTER,
        Key::RIGHT, Key::DOWN, Key::DOWN, Key::SPACE, Key::LEFT, Key::LEFT,
        Key::DOWN, Key::DOWN, Key::DOWN, Key::DOWN, Key::RIGHT, Key::DOWN, Key::DOWN, Key::SPACE, Key::ENTER,
        'n', Key::ENTER,
    ]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe($before);

    Prompt::assertStrippedOutputContains('Turned on:');
    Prompt::assertStrippedOutputContains('  ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval');
    Prompt::assertStrippedOutputContains('Turned off:');
    Prompt::assertStrippedOutputContains('  ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes');
    Prompt::assertStrippedOutputContains('Write the selection to guardrails.php?');
    Prompt::assertStrippedOutputDoesntContain('Selected ');
});

it('turns a configured check off and removes only its entry once the developer confirms', function () {
    $directory = projectSelecting(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

    return [
        // Agents read every file whole.
        new FilesWithinTokenBudget(limit: 8000),
        NoEmDashes::class,
    ];

    PHP);

    install($directory, [Key::ENTER, Key::RIGHT, Key::DOWN, Key::SPACE, Key::ENTER, Key::ENTER]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

    return [
        NoEmDashes::class,
    ];

    PHP);

    Prompt::assertStrippedOutputContains('Turned off:');
    Prompt::assertStrippedOutputContains('  ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget');
    Prompt::assertStrippedOutputContains('Selected 1 check in guardrails.php.');
});

it('keeps a configured check as it is when it is turned off and on again', function () {
    $directory = projectSelecting(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;

    return [
        new FilesWithinTokenBudget(limit: 8000),
    ];

    PHP);

    $before = file_get_contents($directory.'/guardrails.php');

    install($directory, [Key::ENTER, Key::RIGHT, Key::DOWN, Key::SPACE, Key::SPACE, Key::ENTER]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe($before);

    Prompt::assertStrippedOutputContains('The selection is unchanged.');
});

it('offers the groups of an installed pack and of the project itself under their package, after the built-in ones', function () {
    $directory = project(
        ['name' => 'acme/app', 'extra' => ['guardrails' => ['provides' => ['checks' => ['ClaudioDekker\LaravelGuardrails\Tests\Support\NoEmDashes']]]]],
        [installedPack('acme/pack', ['ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine', 'Acme\Pack\Missing'], ['safe' => ['ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine']])],
    );

    install($directory, [
        Key::CTRL_A, Key::CTRL_A, Key::ENTER,
        "\e[F", Key::SPACE, Key::UP, Key::RIGHT, Key::DOWN, Key::SPACE, Key::ENTER,
        Key::ENTER,
    ]);

    expect(guardrails($directory))->toBe([
        'ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine',
        'ClaudioDekker\LaravelGuardrails\Tests\Support\NoEmDashes',
    ]);

    Prompt::assertStrippedOutputContains('safe (acme/pack), 1 checks');
    Prompt::assertStrippedOutputContains('› ▸ ◻ acme/pack        0 of 1 on');
    Prompt::assertStrippedOutputContains('› ▸ ◼ acme/app         1 of 1 on');
    Prompt::assertStrippedOutputContains('›     ◼ EveryLine');
    Prompt::assertStrippedOutputContains('EveryLine  every file');
    Prompt::assertStrippedOutputContains('Every line breaks the rule.');
    Prompt::assertStrippedOutputContains('acme/pack declares Acme\Pack\Missing, which does not exist or is not a check, so it is not offered.');
});

it('writes a preset and extra checks from the flags without asking, and installs the hook', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $result = $repository->runGuardrails('install', '--preset=laravel', '--check=ClaudioDekker\LaravelGuardrails\Checks\Php\Enums\SortedEnumCases');

        expect($result->exitCode)->toBe(0)
            ->and($result->errorOutput)->toBe('')
            ->and($result->output)->toStartWith("Added 19 checks:\n  ")
            ->and($result->output)->toEndWith("Selected 19 checks in guardrails.php.\nInstalled the pre-commit hook at {$repository->hookPath()}.\n")
            ->and(guardrails($repository->path))->toBe(array_values(array_intersect(builtIn('opinionated'), [...builtIn('laravel'), 'ClaudioDekker\LaravelGuardrails\Checks\Php\Enums\SortedEnumCases'])))
            ->and($repository->runGuardrails('--all')->output)->toBe("Guardrails passed: 19 checks over 0 files (every tracked file).\n");
    });
});

it('writes the checks from the flags alone, in catalog order', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->runGuardrails('install', '--check=ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval', '--check=ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes'));

    expect($result->exitCode)->toBe(0)
        ->and($result->output)->toStartWith("Added 2 checks:\n  ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes\n  ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval\nSelected 2 checks in guardrails.php.\n");
});

it('writes the same selection twice', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->runGuardrails('install', '--preset=essentials')->throw();
        $first = file_get_contents($repository->path.'/guardrails.php');

        $result = $repository->runGuardrails('install', '--preset=essentials');

        expect($result->output)->toBe("Every check asked for was already selected.\nSelected 9 checks in guardrails.php.\nThe pre-commit hook is in place at {$repository->hookPath()}.\n")
            ->and(file_get_contents($repository->path.'/guardrails.php'))->toBe($first)
            ->and(is_file($repository->path.'/composer.json'))->toBeFalse();
    });
});

it('refuses to run without a terminal and without flags, naming the flags', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository->runGuardrails('install'));

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toBe("No terminal to ask in. Pass --preset=<name> or --check=<class>, or both, to choose the checks without prompts.\n");
});

it('refuses a preset that does not exist, a check that does not exist, and an unknown argument', function (array $arguments, string $message) {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository->runGuardrails('install', ...$arguments));

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toContain($message);
})->with([
    'a preset' => [['--preset=relaxed'], 'Preset relaxed does not exist. The presets are: claudiodekker/laravel-guardrails:essentials,'],
    'a check' => [['--check=Acme\Pack\NoFoo'], 'Check class Acme\Pack\NoFoo does not exist.'],
    'an argument' => [['--all'], 'Unknown argument --all. Usage: guardrails install [--preset=<name>]... [--check=<class>]... [--help]'],
    'an empty preset' => [['--preset='], '--preset needs a value, such as --preset=<name>.'],
    'an empty check' => [['--check='], '--check needs a value, such as --check=<class>.'],
]);

it('prints the message of an error a check raises while it is chosen, and exits with code 2', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->runGuardrails('install', '--check=ClaudioDekker\LaravelGuardrails\Tests\Support\Unavailable'));

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe("The licence server is down.\n");
});

it('refuses a check from a package composer.json does not discover', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select([], ['dont-discover' => ['claudiodekker/laravel-guardrails']])
        ->runGuardrails('install', '--check=ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval'));

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toStartWith('ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval comes from claudiodekker/laravel-guardrails, which extra.guardrails.dont-discover ignores through its entry claudiodekker/laravel-guardrails.');
});

it('starts from a preset when guardrails.php selects nothing', function () {
    $directory = projectSelecting("<?php\n\nreturn [];\n");

    install($directory, [Key::ENTER, Key::ENTER, Key::ENTER]);

    Prompt::assertStrippedOutputDoesntContain('Your current selection');
    Prompt::assertStrippedOutputContains('essentials (claudiodekker/laravel-guardrails), 9 checks');
});

it('still adds to a guardrails.php that fails to load, keeping its entries', function () {
    $result = ScratchRepository::using(function (ScratchRepository $repository) {
        $result = $repository
            ->write('guardrails.php', "<?php\n\nreturn [\n    new Acme\\Pack\\NoFoo(limit: 1),\n    \\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes::class,\n];\n")
            ->runGuardrails('install', '--check=ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval');

        expect(file_get_contents($repository->path.'/guardrails.php'))->toContain('new Acme\\Pack\\NoFoo(limit: 1),')->toContain('NoEmDashes::class,')->toContain('NoEval::class,');

        return $result;
    });

    expect($result->exitCode)->toBe(0)
        ->and($result->errorOutput)->toContain('guardrails.php could not be loaded: Class "Acme\\Pack\\NoFoo" not found')
        ->and($result->output)->toStartWith("Added 1 check:\n  ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval\nSelected 3 checks in guardrails.php.\n");
});

it('refuses a check from the flags whose constructor takes options, naming the way to write it', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->runGuardrails('install', '--check=ClaudioDekker\LaravelGuardrails\Tests\Support\OnlyLine'));

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toBe("ClaudioDekker\\LaravelGuardrails\\Tests\\Support\\OnlyLine takes options. Write it as new OnlyLine(...) in guardrails.php.\n");
});

it('takes the union of the presets named by repeated --preset flags', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $result = $repository->runGuardrails('install', '--preset=essentials', '--preset=laravel');

        $union = array_values(array_unique([...builtIn('essentials'), ...builtIn('laravel')]));

        expect($result->exitCode)->toBe(0)
            ->and($result->output)->toContain('Selected '.count($union)." checks in guardrails.php.\n")
            ->and(guardrails($repository->path))->toBe(array_values(array_intersect(builtIn('opinionated'), $union)));
    });
});

it('prints its usage and exits with code 0 for --help', function (string $command, string $usage) {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository->runGuardrails($command, '--help'));

    expect($result->exitCode)->toBe(0)
        ->and($result->errorOutput)->toBe('')
        ->and(str_starts_with($result->output, $usage))->toBeTrue();
})->with([
    'install' => ['install', 'Usage: guardrails install [--preset=<name>]... [--check=<class>]...'],
    'list' => ['list', 'Usage: guardrails list [--details]'],
]);

it('adds the flags to the entries guardrails.php has, and leaves those entries as they are', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $entries = "    // Agents read every file whole.\n    (new FilesWithinTokenBudget(limit: 8000))->as('Budget')->only('app'),\n    NoEmDashes::class,\n";

        $result = $repository
            ->write('guardrails.php', "<?php\n\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\FilesWithinTokenBudget;\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes;\n\nreturn [\n{$entries}];\n")
            ->runGuardrails('install', '--check=ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval');

        expect($result->exitCode)->toBe(0)
            ->and($result->output)->toStartWith("Added 1 check:\n  ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval\nSelected 3 checks in guardrails.php.\n")
            ->and(file_get_contents($repository->path.'/guardrails.php'))->toContain($entries)
            ->and(guardrails($repository->path))->toContain('ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget', 'ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes', 'ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval');
    });
});

it('does not remove the checks a preset leaves out when the flags add that preset', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->runGuardrails('install', '--preset=essentials')->throw();
        $before = guardrails($repository->path);

        $repository->runGuardrails('install', '--check=ClaudioDekker\LaravelGuardrails\Checks\Php\Enums\SortedEnumCases')->throw();

        expect(guardrails($repository->path))->toEqualCanonicalizing([...$before, 'ClaudioDekker\LaravelGuardrails\Checks\Php\Enums\SortedEnumCases']);
    });
});

it('leaves out of the picker a check a preset names that is not offered, saying why', function () {
    $directory = project(
        ['name' => 'acme/app', 'extra' => ['guardrails' => ['dont-discover' => ['acme/ignored']]]],
        [
            installedPack('acme/pack', ['ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine'], ['safe' => ['ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine', 'Acme\Pack\Missing', 'ClaudioDekker\LaravelGuardrails\Tests\Support\OnlyLine']]),
            installedPack('acme/ignored', ['ClaudioDekker\LaravelGuardrails\Tests\Support\OnlyLine']),
        ],
    );

    install($directory, [Key::CTRL_A, Key::ENTER, Key::ENTER, Key::ENTER]);

    $selected = guardrails($directory);

    expect(in_array('ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine', $selected, true))->toBeTrue()
        ->and(in_array('Acme\Pack\Missing', $selected, true))->toBeFalse()
        ->and(in_array('ClaudioDekker\LaravelGuardrails\Tests\Support\OnlyLine', $selected, true))->toBeFalse();

    Prompt::assertStrippedOutputContains('Left out:');
    Prompt::assertStrippedOutputContains('Acme\Pack\Missing, because it does not exist or is not a check');
    Prompt::assertStrippedOutputContains('ClaudioDekker\LaravelGuardrails\Tests\Support\OnlyLine, because its package is not discovered');
});

it('refuses a preset description for a preset the pack does not provide, naming both', function () {
    $result = listChecks(project(['name' => 'acme/app'], [installedPack('acme/pack', [], ['safe' => []], ['sfae' => 'A typo.'])]));

    expect($result['exitCode'])->toBe(2)
        ->and($result['errorOutput'])->toContain('acme/pack: extra.guardrails.provides.descriptions describes the preset sfae, which the pack does not provide.');
});
