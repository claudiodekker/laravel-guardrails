<?php

use ClaudioDekker\LaravelGuardrails\Catalog;
use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\OneClassPerFile;
use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
use ClaudioDekker\LaravelGuardrails\Configuration;
use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;
use ClaudioDekker\LaravelGuardrails\Tests\Support\Explained;

it('selects nothing when there is no guardrails.php or it returns an empty list', function (?string $contents) {
    $directory = $contents === null ? project(['name' => 'acme/app']) : projectSelecting($contents);

    $configuration = Configuration::fromProject($directory);

    expect($configuration->checks)->toBe([])
        ->and($configuration->checks(Catalog::discover($directory)))->toBe([]);
})->with([
    'no file' => null,
    'an empty list' => "<?php\n\nreturn [];\n",
]);

it('runs the class names and the instances in the order guardrails.php lists them, built in or not', function () {
    $directory = projectSelecting(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\OneClassPerFile;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
    use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;
    use ClaudioDekker\LaravelGuardrails\Tests\Support\Explained;

    return [
        new FilesWithinTokenBudget(limit: 8000),
        Explained::class,
        new EveryLine(null, ['*.md']),
        OneClassPerFile::class,
    ];

    PHP);

    $configuration = Configuration::fromProject($directory);
    $checks = $configuration->checks(Catalog::discover($directory));

    expect(array_map(fn (Check $check) => $check::class, $checks))->toBe([FilesWithinTokenBudget::class, Explained::class, EveryLine::class, OneClassPerFile::class])
        ->and($checks[0])->toBe($configuration->checks[0])
        ->and($checks[0]->description())->toBe('Every file stays under 8000 tokens, so an agent can read it whole.')
        ->and($checks[2]->scope())->toBe('*.md')
        ->and($configuration->classes())->toBe([FilesWithinTokenBudget::class, Explained::class, EveryLine::class, OneClassPerFile::class])
        ->and($configuration->instances())->toBe([$checks[0], $checks[2]]);
});

it('refuses a selected check whose class does not exist, naming both fixes', function () {
    $configuration = Configuration::fromProject(projectSelecting("<?php\n\nreturn ['Acme\\\\Pack\\\\NoFoo'];\n"));

    expect(fn () => $configuration->checks(Catalog::discover(project())))
        ->toThrow(InvalidArgumentException::class, 'Check class Acme\\Pack\\NoFoo does not exist. Require the package that provides it again, or run vendor/bin/guardrails install to deselect it.');
});

it('refuses a selected check from a package it does not discover, as a class name or as an instance, naming both fixes', function (string $entry, array $dontDiscover, string $matched) {
    $directory = projectSelecting("<?php\n\nreturn [{$entry}];\n", ['extra' => ['guardrails' => ['dont-discover' => $dontDiscover]]]);
    $configuration = Configuration::fromProject($directory);

    expect(fn () => $configuration->checks(Catalog::discover($directory)))
        ->toThrow(InvalidArgumentException::class, "ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes comes from claudiodekker/laravel-guardrails, which extra.guardrails.dont-discover ignores through its entry {$matched}. Remove {$matched} from dont-discover, or run vendor/bin/guardrails install to deselect it.");
})->with([
    'a class name, by package name' => ['\ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes::class', ['claudiodekker/laravel-guardrails'], 'claudiodekker/laravel-guardrails'],
    'an instance, by every package' => ['new \ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes', ['*'], '*'],
]);

it('discovers every pack but the ones it does not want', function () {
    $directory = project(['extra' => ['guardrails' => ['dont-discover' => ['acme/pack']]]], [installedPack('acme/pack', ['Acme\\Pack\\NoFoo'])]);
    $configuration = Configuration::fromProject($directory);
    $catalog = Catalog::discover($directory);

    expect($configuration->discoverable($catalog)->pack('acme/pack'))->toBeNull()
        ->and($catalog->pack('acme/pack'))->not->toBeNull();
});

it('refuses a check class that is not a check', function () {
    $configuration = Configuration::fromProject(projectSelecting("<?php\n\nreturn [\\ClaudioDekker\\LaravelGuardrails\\Configuration::class];\n"));

    expect(fn () => $configuration->checks(Catalog::discover(project())))
        ->toThrow(InvalidArgumentException::class, 'must extend ClaudioDekker\\LaravelGuardrails\\Check.');
});

it('refuses an entry that is neither a class name nor a check, naming its position', function (string $entry) {
    $directory = projectSelecting("<?php\n\nreturn [\\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes::class, {$entry}];\n");

    expect(fn () => Configuration::fromProject($directory))
        ->toThrow(InvalidArgumentException::class, "{$directory}/guardrails.php: entry 2 must be a check class name or a check instance.");
})->with([
    'a number' => '1',
    'an object' => 'new \stdClass',
    'a list' => '[]',
]);

it('refuses a guardrails.php that does not return a list', function (string $contents) {
    $directory = projectSelecting($contents);

    expect(fn () => Configuration::fromProject($directory))
        ->toThrow(InvalidArgumentException::class, "{$directory}/guardrails.php must return a list of checks.");
})->with([
    'no return' => "<?php\n",
    'a string' => "<?php\n\nreturn 'NoEmDashes';\n",
    'a map' => "<?php\n\nreturn ['text' => \\ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes::class];\n",
]);

it('refuses a guardrails.php that fails to load, naming the file and the error', function () {
    $directory = projectSelecting("<?php\n\nreturn [new \\Acme\\Pack\\NoFoo(limit: 1)];\n");

    expect(fn () => Configuration::fromProject($directory))
        ->toThrow(InvalidArgumentException::class, "{$directory}/guardrails.php could not be loaded: Class \"Acme\\Pack\\NoFoo\" not found");
});

it('refuses a guardrails.php that selects a check by its class name and again, naming the entries', function (string $first, string $second) {
    $directory = projectSelecting("<?php\n\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes;\n\nreturn [\n    {$first},\n    {$second},\n];\n");

    expect(fn () => Configuration::fromProject($directory))
        ->toThrow(InvalidArgumentException::class, "{$directory}/guardrails.php: entry 2 selects ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes again, after entry 1. Keep one entry per check, or configure each one.");
})->with([
    'as a class name again' => ['NoEmDashes::class', "'ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes'"],
    'as an instance after its class name' => ['NoEmDashes::class', '(new NoEmDashes)->only(\'app\')'],
    'as its class name after an instance' => ['(new NoEmDashes)->only(\'app\')', 'NoEmDashes::class'],
]);

it('selects a check as several instances, each configured its own way', function () {
    $directory = projectSelecting("<?php\n\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Structure\\NoNamespaces;\n\nreturn [\n    (new NoNamespaces(['Acme\\Billing']))->only('packages/crm/src'),\n    (new NoNamespaces(['Acme\\Crm']))->only('packages/billing/src'),\n];\n");

    $checks = Configuration::fromProject($directory)->checks(Catalog::discover($directory));

    expect(array_map(fn (Check $check) => [$check::name(), $check->description(), $check->coverage()], $checks))->toBe([
        ['NoNamespaces', 'No name from Acme\Billing.', 'PHP, under packages/crm/src'],
        ['NoNamespaces', 'No name from Acme\Crm.', 'PHP, under packages/billing/src'],
    ]);
});

it('refuses a class name of a check whose constructor takes options, naming the way to write it', function () {
    $configuration = Configuration::fromProject(projectSelecting("<?php\n\nreturn [\\ClaudioDekker\\LaravelGuardrails\\Tests\\Support\\OnlyLine::class];\n"));

    expect(fn () => $configuration->checks(Catalog::discover(project())))
        ->toThrow(InvalidArgumentException::class, 'ClaudioDekker\\LaravelGuardrails\\Tests\\Support\\OnlyLine takes options. Write it as new OnlyLine(...) in guardrails.php.');
});

it('refuses a dont-discover value in composer.json that is not a list of names', function (array $guardrails, string $key) {
    expect(fn () => Configuration::fromProject(project(['extra' => ['guardrails' => $guardrails]])))
        ->toThrow(InvalidArgumentException::class, "extra.guardrails.{$key} must be a list of class names.");
})->with([
    'dont-discover as a string' => [['dont-discover' => '*'], 'dont-discover'],
]);

it('refuses a guardrails key in composer.json that is not an object', function () {
    $directory = projectWith(['composer.json' => '{"extra": {"guardrails": "checks"}}']);

    expect(fn () => Configuration::fromProject($directory))
        ->toThrow(InvalidArgumentException::class, 'extra.guardrails must be an object.');
});

it('refuses a composer.json that is not valid JSON', function () {
    $directory = projectWith(['composer.json' => '{']);

    expect(fn () => Configuration::fromProject($directory))
        ->toThrow(InvalidArgumentException::class, 'is not valid JSON');
});
