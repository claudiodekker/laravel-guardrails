<?php

use ClaudioDekker\LaravelGuardrails\Catalog;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\NoTestCodeInApp;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\OneClassPerFile;
use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;
use ClaudioDekker\LaravelGuardrails\Pack;
use ClaudioDekker\LaravelGuardrails\Preset;

it('offers the built-in checks and presets first, even in a project with no composer.json', function () {
    $catalog = Catalog::discover(project());

    expect(array_map(fn (Pack $pack) => $pack->name, $catalog->packs))->toBe(['claudiodekker/laravel-guardrails'])
        ->and($catalog->checks())->toContain(NoEmDashes::class, NoTestCodeInApp::class)
        ->and(array_map(fn (Preset $preset) => $preset->id(), $catalog->presets()))->toBe([
            'claudiodekker/laravel-guardrails:essentials',
            'claudiodekker/laravel-guardrails:app',
            'claudiodekker/laravel-guardrails:package',
            'claudiodekker/laravel-guardrails:laravel',
            'claudiodekker/laravel-guardrails:opinionated',
        ]);
});

it('offers every built-in check in a built-in preset, but two that the developer picks by hand', function () {
    $catalog = Catalog::discover(project());
    $built = $catalog->checks();
    $inNoPreset = [
        'ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\NoReflection',
        'ClaudioDekker\LaravelGuardrails\Checks\Php\Config\SortedConfigKeys',
    ];

    expect($catalog->preset('opinionated')->checks)->toBe(array_values(array_diff($built, $inNoPreset)))
        ->and($catalog->preset('essentials')->checks)->not->toBe($built);

    foreach ($built as $class) {
        expect(class_exists($class))->toBeTrue("{$class} is declared under provides but does not exist");
    }
});

it('offers the app and package presets as the essentials plus the checks of that audience, in catalog order', function () {
    $catalog = Catalog::discover(project());
    $essentials = $catalog->preset('essentials')->checks;
    $namespace = 'ClaudioDekker\LaravelGuardrails\Checks\Php\\';

    expect(array_values(array_diff($catalog->preset('app')->checks, $essentials)))->toBe([
        $namespace.'Tests\NoHttpInUnitTests',
        $namespace.'Structure\ConcernsHoldOnlyTraits',
        $namespace.'Safety\NoWeakHashes',
        $namespace.'Safety\NoWeakRandomness',
        $namespace.'Config\NoConfigDefaults',
        $namespace.'Config\DurationsAsProducts',
        $namespace.'Database\NoFloatMoneyColumns',
    ])
        ->and(array_values(array_diff($catalog->preset('package')->checks, $essentials)))->toBe([
            $namespace.'Tests\NoHttpInUnitTests',
            $namespace.'Structure\ConcernsHoldOnlyTraits',
            $namespace.'Structure\NoFinal',
            $namespace.'Config\NoConfigDefaults',
            $namespace.'Config\DurationsAsProducts',
        ])
        ->and(array_values(array_diff($catalog->preset('laravel')->checks, $essentials)))->toBe([
            'ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes',
            $namespace.'Structure\ConcernsHoldOnlyTraits',
            $namespace.'Structure\NoFinal',
            $namespace.'Shape\NoBlankLineOpeningBlocks',
            $namespace.'Shape\BlankLineAfterMultilineStatements',
            $namespace.'Comments\SlopedComments',
            $namespace.'Comments\SingleCaveatDocblocks',
            $namespace.'Comments\DocumentedDeclarations',
        ])
        ->and(array_values(array_intersect($essentials, $catalog->preset('app')->checks)))->toBe($essentials)
        ->and(array_values(array_intersect($essentials, $catalog->preset('package')->checks)))->toBe($essentials)
        ->and(array_values(array_intersect($essentials, $catalog->preset('laravel')->checks)))->toBe($essentials)
        ->and($catalog->preset('opinionated')->checks)->toContain($namespace.'Tests\TestsInUnitOrFeature')
        ->and($essentials)->not->toContain($namespace.'Tests\TestsInUnitOrFeature');
});

it('discovers the checks and presets an installed package provides, after the built-in ones', function () {
    $catalog = Catalog::discover(project(
        ['name' => 'acme/app'],
        [['name' => 'acme/other', 'version' => '1.0.0'], installedPack('acme/pack', ['Acme\Pack\NoFoo'], ['safe' => ['Acme\Pack\NoFoo', OneClassPerFile::class]])],
    ));

    expect(array_map(fn (Pack $pack) => $pack->name, $catalog->packs))->toBe(['claudiodekker/laravel-guardrails', 'acme/pack'])
        ->and($catalog->packs[1]->checks)->toBe(['Acme\Pack\NoFoo'])
        ->and($catalog->preset('acme/pack:safe')->checks)->toBe(['Acme\Pack\NoFoo', OneClassPerFile::class])
        ->and($catalog->preset('safe')->id())->toBe('acme/pack:safe')
        ->and($catalog->provider('Acme\Pack\NoFoo')?->name)->toBe('acme/pack')
        ->and($catalog->provider(OneClassPerFile::class)?->name)->toBe('claudiodekker/laravel-guardrails')
        ->and($catalog->provider('App\Guardrails\Missing'))->toBeNull();
});

it('discovers the checks the project itself provides, last', function (array $manifest, string $name) {
    $catalog = Catalog::discover(project([...$manifest, 'extra' => ['guardrails' => ['provides' => ['checks' => ['App\Guardrails\NoDateFunctions']]]]], [installedPack('acme/pack', ['Acme\Pack\NoFoo'])]));

    expect(array_map(fn (Pack $pack) => $pack->name, $catalog->packs))->toBe(['claudiodekker/laravel-guardrails', 'acme/pack', $name])
        ->and($catalog->pack($name)?->checks)->toBe(['App\Guardrails\NoDateFunctions']);
})->with([
    'a named project' => [['name' => 'acme/app'], 'acme/app'],
    'a project without a name' => [[], '__root__'],
]);

it('reads its own pack once, from its own composer.json, when it is installed or is the project', function () {
    $installed = Catalog::discover(project(['name' => 'acme/app'], [installedPack('claudiodekker/laravel-guardrails', ['Stale\Check'])]));
    $itself = Catalog::discover(dirname(__DIR__, 2));

    expect(array_map(fn (Pack $pack) => $pack->name, $installed->packs))->toBe(['claudiodekker/laravel-guardrails'])
        ->and($installed->checks())->not->toContain('Stale\Check')
        ->and(array_map(fn (Pack $pack) => $pack->name, $itself->packs))->toBe(['claudiodekker/laravel-guardrails']);
});

it('tells two packs that name a check alike apart by the full class name', function () {
    $catalog = Catalog::discover(project([], [installedPack('acme/pack', ['Acme\Pack\NoFoo']), installedPack('acme/other', ['Acme\Other\NoFoo'])]));

    expect($catalog->checks())->toContain('Acme\Pack\NoFoo', 'Acme\Other\NoFoo')
        ->and($catalog->provider('Acme\Other\NoFoo')?->name)->toBe('acme/other');
});

it('leaves out the packs the project does not want to discover', function (array $dontDiscover, array $packs) {
    /** @var list<string> $dontDiscover */
    $catalog = Catalog::discover(project(
        ['name' => 'acme/app', 'extra' => ['guardrails' => ['provides' => ['checks' => ['App\Guardrails\NoDateFunctions']]]]],
        [installedPack('acme/pack', ['Acme\Pack\NoFoo']), installedPack('acme/other', ['Acme\Other\NoBar'])],
    ));

    expect(array_map(fn (Pack $pack) => $pack->name, $catalog->without($dontDiscover)->packs))->toBe($packs);
})->with([
    'one pack' => [['acme/pack'], ['claudiodekker/laravel-guardrails', 'acme/other', 'acme/app']],
    'the built-in pack' => [['claudiodekker/laravel-guardrails'], ['acme/pack', 'acme/other', 'acme/app']],
    'every package' => [['*'], ['acme/app']],
]);

it('reads the installed packages from a custom vendor directory', function () {
    $catalog = Catalog::discover(project(['config' => ['vendor-dir' => 'deps']], [installedPack('acme/pack', ['Acme\Pack\NoFoo'])], 'deps'));

    expect($catalog->provider('Acme\Pack\NoFoo')?->name)->toBe('acme/pack');
});

it('refuses a preset that does not exist, naming the presets in full', function () {
    $catalog = Catalog::discover(project([], [installedPack('acme/pack', [], ['safe' => []])]));

    expect(fn () => $catalog->preset('relaxed'))
        ->toThrow(InvalidArgumentException::class, 'Preset relaxed does not exist. The presets are: claudiodekker/laravel-guardrails:essentials, claudiodekker/laravel-guardrails:app, claudiodekker/laravel-guardrails:package, claudiodekker/laravel-guardrails:laravel, claudiodekker/laravel-guardrails:opinionated, acme/pack:safe.');
});

it('refuses a preset name two packs use, unless it is named in full', function () {
    $catalog = Catalog::discover(project([], [installedPack('acme/pack', ['Acme\Pack\NoFoo'], ['essentials' => ['Acme\Pack\NoFoo']])]));

    expect(fn () => $catalog->preset('essentials'))
        ->toThrow(InvalidArgumentException::class, 'Preset essentials is ambiguous. Name it in full: claudiodekker/laravel-guardrails:essentials, acme/pack:essentials.')
        ->and($catalog->preset('acme/pack:essentials')->checks)->toBe(['Acme\Pack\NoFoo']);
});

it('refuses a pack that declares its checks or presets wrongly, naming the package', function (array $provides, string $message) {
    $path = project([], [['name' => 'acme/pack', 'version' => '1.0.0', 'extra' => ['guardrails' => ['provides' => $provides]]]]);

    expect(fn () => Catalog::discover($path))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'checks as a string' => [['checks' => 'Acme\Pack\NoFoo'], 'acme/pack: extra.guardrails.provides.checks must be a list of class names.'],
    'a preset as a string' => [['presets' => ['safe' => 'Acme\Pack\NoFoo']], 'acme/pack: extra.guardrails.provides.presets.safe must be a list of class names.'],
    'presets as a list' => [['presets' => [['Acme\Pack\NoFoo']]], 'acme/pack: extra.guardrails.provides.presets must map each preset name to a list of class names.'],
    'provides as a list' => [['Acme\Pack\NoFoo'], 'acme/pack: extra.guardrails.provides must be an object with "checks" and "presets".'],
]);

it('reads the description of each built-in preset, one line saying what it is for', function () {
    $presets = Catalog::discover(project())->presets();

    foreach ($presets as $preset) {
        expect($preset->description)->not->toBe('')
            ->and($preset->description)->not->toContain("\n");
    }

    expect($presets[0]->label())->toStartWith('essentials (claudiodekker/laravel-guardrails), 9 checks: ');
});

it('reads the description of a preset of an installed pack, and none when it gives none', function () {
    $catalog = Catalog::discover(project([], [installedPack('acme/pack', ['Acme\Pack\NoFoo'], ['safe' => ['Acme\Pack\NoFoo'], 'loud' => ['Acme\Pack\NoFoo']], ['safe' => 'Keep it safe.'])]));

    expect($catalog->preset('safe')->label())->toBe('safe (acme/pack), 1 checks: Keep it safe.')
        ->and($catalog->preset('loud')->label())->toBe('loud (acme/pack), 1 checks');
});

it('refuses descriptions that are not a map of sentences', function () {
    expect(fn () => Catalog::discover(project([], [['name' => 'acme/pack', 'extra' => ['guardrails' => ['provides' => ['descriptions' => ['safe' => ['Keep it safe.']]]]]]])))
        ->toThrow(InvalidArgumentException::class, 'acme/pack: extra.guardrails.provides.descriptions must map each preset name to a sentence.');
});
