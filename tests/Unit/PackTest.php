<?php

use ClaudioDekker\LaravelGuardrails\Catalog;
use ClaudioDekker\LaravelGuardrails\Group;
use ClaudioDekker\LaravelGuardrails\Pack;
use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;
use ClaudioDekker\LaravelGuardrails\Tests\Support\Nested\EveryLine as NestedEveryLine;
use ClaudioDekker\LaravelGuardrails\Tests\Support\NoEmDashes;

it('groups the built-in checks by their namespace under Checks, in the order they are declared', function () {
    $groups = Catalog::discover(project())->packs[0]->groups();

    expect(array_map(fn (Group $group) => $group->label(), $groups))->toBe([
        'Text',
        'Php › Tests',
        'Php › Structure',
        'Php › Enums',
        'Php › Safety',
        'Php › Shape',
        'Php › Comments',
        'Php › Config',
        'Php › Database',
        'Php › Package',
    ])
        ->and($groups[0]->checks)->toBe([
            'ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget',
            'ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes',
        ]);
});

it('groups the checks of another pack below the namespace they share, labelled by the package', function () {
    $pack = new Pack('acme/pack', [EveryLine::class, NoEmDashes::class, NestedEveryLine::class, 'Acme\Pack\Missing']);

    $groups = $pack->groups();

    expect(array_map(fn (Group $group) => [$group->name, $group->label(), $group->checks], $groups))->toBe([
        ['', 'acme/pack', [EveryLine::class, NoEmDashes::class]],
        ['Nested', 'acme/pack › Nested', [NestedEveryLine::class]],
    ])
        ->and($pack->existing())->toBe([EveryLine::class, NoEmDashes::class, NestedEveryLine::class])
        ->and($pack->missing())->toBe(['Acme\Pack\Missing']);
});

it('puts the checks of a pack whose namespaces only share a root in a group per namespace', function () {
    $pack = new Pack('acme/app', [NestedEveryLine::class, 'ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes'], own: true);

    expect(array_map(fn (Group $group) => $group->label(), $pack->groups()))->toBe(['acme/app › Tests › Support › Nested', 'acme/app › Checks › Text']);
});

it('counts the checks of a group that a selection holds', function () {
    $group = new Group(new Pack('acme/pack'), '', [EveryLine::class, NoEmDashes::class]);

    expect($group->selected([NoEmDashes::class, 'Acme\Other']))->toBe([NoEmDashes::class])
        ->and($group->selected([]))->toBe([]);
});

it('explains what the checks of each built-in group share, and names the package of any other group', function () {
    $builtIn = Catalog::discover(project())->packs[0]->groups();
    $pack = new Pack('acme/pack', [EveryLine::class, NestedEveryLine::class]);

    expect(array_filter($builtIn, fn (Group $group) => $group->explanation() === '' || str_contains($group->explanation(), 'claudiodekker/laravel-guardrails')))->toBe([])
        ->and(count(array_unique(array_map(fn (Group $group) => $group->explanation(), $builtIn))))->toBe(count($builtIn))
        ->and(array_map(fn (Group $group) => $group->explanation(), $pack->groups()))->toBe([
            'The checks acme/pack provides.',
            'The checks acme/pack provides.',
        ]);
});
