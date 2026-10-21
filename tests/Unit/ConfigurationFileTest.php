<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoUnserialize;
use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;
use ClaudioDekker\LaravelGuardrails\ConfigurationFile;

it('writes a fresh file with one import per class and one entry per line, under the header', function () {
    $directory = projectWith([]);

    ConfigurationFile::read($directory)->select([FilesWithinTokenBudget::class, NoDebugCalls::class]);

    $source = (string) file_get_contents($directory.'/guardrails.php');

    expect($source)->toStartWith("<?php\n\n/*\n|----")
        ->and(substr_count($source, '| Laravel Guardrails Checks'))->toBe(1)
        ->and(substr($source, strpos($source, "*/\n\nuse") + 4))->toBe(<<<'PHP'
    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;

    return [
        FilesWithinTokenBudget::class,
        NoDebugCalls::class,
    ];

    PHP);
});

it('reads the classes of the entries, instances included, and ignores hand-written entries', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes as Dashes;

    return [
        new FilesWithinTokenBudget(limit: 8000),
        Dashes::class,
        \ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval::class,
        NoDebugCalls::class,
        ...acme_checks(),
        new $dynamic,
    ];

    PHP]);

    $file = ConfigurationFile::read($directory);

    expect($file->classes())->toBe([FilesWithinTokenBudget::class, NoEmDashes::class, NoEval::class, NoDebugCalls::class])
        ->and($file->configured())->toBe([FilesWithinTokenBudget::class]);
});

it('reads no classes when there is no file', function () {
    $file = ConfigurationFile::read(projectWith([]));

    expect($file->classes())->toBe([])
        ->and($file->configured())->toBe([]);
});

it('keeps an instance, comments on their own lines, blank lines and hand-written entries as they are on a rerun', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    declare(strict_types=1);

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;

    return [
        // Agents read every file whole.
        new FilesWithinTokenBudget(limit: 8000),

        /* The checks of the team. */
        NoDebugCalls::class,
        ...acme_checks(),
        acme_check('strict'),
    ];

    PHP]);

    $before = file_get_contents($directory.'/guardrails.php');

    ConfigurationFile::read($directory)->select([FilesWithinTokenBudget::class, NoDebugCalls::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe($before);
});

it('drops a deselected class entry and a deselected instance, each with its import and the comment above it', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

    return [
        // Agents read every file whole.
        new FilesWithinTokenBudget(limit: 8000),
        NoEmDashes::class,

        NoDebugCalls::class,
    ];

    PHP]);

    ConfigurationFile::read($directory)->select([NoDebugCalls::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;

    return [
        NoDebugCalls::class,
    ];

    PHP);
});

it('inserts a new entry after the selected entry that precedes it, or before the first that follows it, with its import in sorted position', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

    return [
        NoEmDashes::class,
        ...acme_checks(),
        NoDebugCalls::class,
    ];

    PHP]);

    ConfigurationFile::read($directory)->select([FilesWithinTokenBudget::class, NoEmDashes::class, NoDebugCalls::class, NoEval::class, NoUnserialize::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval;
    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoUnserialize;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

    return [
        FilesWithinTokenBudget::class,
        NoEmDashes::class,
        ...acme_checks(),
        NoDebugCalls::class,
        NoEval::class,
        NoUnserialize::class,
    ];

    PHP);
});

it('appends a new entry after the hand-written entries when the file selects nothing yet', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    use Acme\Guardrails\Checks;

    return [
        ...Checks::all(),
    ];

    PHP]);

    ConfigurationFile::read($directory)->select([NoEval::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    use Acme\Guardrails\Checks;
    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval;

    return [
        ...Checks::all(),
        NoEval::class,
    ];

    PHP);
});

it('refers to a class by the alias its import gives it', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval as Evaluate;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes as Dashes;

    return [
        Dashes::class,
    ];

    PHP]);

    ConfigurationFile::read($directory)->select([NoEmDashes::class, NoEval::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval as Evaluate;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes as Dashes;

    return [
        Dashes::class,
        Evaluate::class,
    ];

    PHP);
});

it('names a class in full when another import already takes its short name', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Tests\Support\NoEmDashes;

    return [
        NoEmDashes::class,
    ];

    PHP]);

    ConfigurationFile::read($directory)->select(['ClaudioDekker\LaravelGuardrails\Tests\Support\NoEmDashes', NoEmDashes::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Tests\Support\NoEmDashes;

    return [
        NoEmDashes::class,
        \ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes::class,
    ];

    PHP);
});

it('adds the imports as a block before the return when the file has none', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    declare(strict_types=1);

    // The checks every app runs.
    return [
        \ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes::class,
    ];

    PHP]);

    ConfigurationFile::read($directory)->select([NoEmDashes::class, NoDebugCalls::class, NoEval::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    declare(strict_types=1);

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval;

    // The checks every app runs.
    return [
        \ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes::class,
        NoDebugCalls::class,
        NoEval::class,
    ];

    PHP);
});

it('prints the entries one per line when the array held fewer than two', function (string $return) {
    $directory = projectWith(['guardrails.php' => "<?php\n\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes;\n\n{$return}\n"]);

    ConfigurationFile::read($directory)->select([NoEmDashes::class, NoDebugCalls::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

    return [
        NoEmDashes::class,
        NoDebugCalls::class,
    ];

    PHP);
})->with([
    'an empty array' => 'return [];',
    'one entry on the same line' => 'return [NoEmDashes::class];',
    'one entry on its own line' => "return [\n    NoEmDashes::class,\n];",
]);

it('keeps a comment inside an empty array, and a comment after the last entry, when it adds entries', function (string $return, string $expected) {
    $directory = projectWith(['guardrails.php' => "<?php\n\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes;\n\n{$return}\n"]);

    ConfigurationFile::read($directory)->select([NoEmDashes::class, NoDebugCalls::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe("<?php\n\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoDebugCalls;\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes;\n\n{$expected}\n");
})->with([
    'a comment in an empty array' => ["return [\n    // Add the checks here.\n];", "return [\n    // Add the checks here.\n    NoEmDashes::class,\n    NoDebugCalls::class,\n];"],
    'a comment after the only entry' => ["return [\n    NoEmDashes::class, // The docs use it.\n];", "return [\n    NoEmDashes::class, // The docs use it.\n    NoDebugCalls::class,\n];"],
    'a comment after the last of two entries' => ["return [\n    new NoEmDashes,\n    NoEmDashes::class, // The docs use it.\n];", "return [\n    new NoEmDashes,\n    NoEmDashes::class, // The docs use it.\n    NoDebugCalls::class,\n];"],
    'an indented array' => ["return\n    [\n        NoEmDashes::class,\n    ];", "return\n    [\n        NoEmDashes::class,\n        NoDebugCalls::class,\n    ];"],
]);

it('refers to a class a group import names, and names a class in full when a group import takes its short name', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    use Acme\Guardrails\{NoDebugCalls, NoFoo};
    use ClaudioDekker\LaravelGuardrails\Checks\Text\{FilesWithinTokenBudget, NoEmDashes};

    return [
        NoFoo::class,
    ];

    PHP]);

    ConfigurationFile::read($directory)->select(['Acme\Guardrails\NoFoo', NoEmDashes::class, NoDebugCalls::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    use Acme\Guardrails\{NoDebugCalls, NoFoo};
    use ClaudioDekker\LaravelGuardrails\Checks\Text\{FilesWithinTokenBudget, NoEmDashes};

    return [
        NoFoo::class,
        NoEmDashes::class,
        \ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls::class,
    ];

    PHP);
});

it('keeps a hand-written import nothing refers to, and drops only the import of an entry it removed', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    use Acme\Guardrails\NoFoo;
    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

    return [
        NoEmDashes::class,
        NoDebugCalls::class,
    ];

    PHP]);

    ConfigurationFile::read($directory)->select([NoEmDashes::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    use Acme\Guardrails\NoFoo;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

    return [
        NoEmDashes::class,
    ];

    PHP);
});

it('reads a quoted class name as an entry of that class', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    return [
        'ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes',
        '\ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls',
    ];

    PHP]);

    $file = ConfigurationFile::read($directory);

    expect($file->classes())->toBe([NoEmDashes::class, NoDebugCalls::class]);

    $file->select([NoDebugCalls::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    return [
        '\ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls',
    ];

    PHP);
});

it('leaves every other import alone', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    use function Acme\acme_checks;
    use const Acme\STRICT;
    use Acme\Guardrails\{NoFoo, NoBar};
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

    return [
        NoEmDashes::class,
    ];

    PHP]);

    ConfigurationFile::read($directory)->select([NoEmDashes::class, NoDebugCalls::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    use function Acme\acme_checks;
    use const Acme\STRICT;
    use Acme\Guardrails\{NoFoo, NoBar};
    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

    return [
        NoEmDashes::class,
        NoDebugCalls::class,
    ];

    PHP);
});

it('refuses a file that does not return a list, naming the fix', function (string $contents) {
    $directory = projectWith(['guardrails.php' => $contents]);

    expect(fn () => ConfigurationFile::read($directory))
        ->toThrow(InvalidArgumentException::class, "{$directory}/guardrails.php must return a list for guardrails install to edit it. Edit the selection by hand.");
})->with([
    'a call' => "<?php\n\nreturn acme_checks();\n",
    'a variable' => "<?php\n\n\$checks = [];\n\nreturn \$checks;\n",
    'no return' => "<?php\n\n\$checks = [];\n",
]);

it('refuses a file that does not parse, naming the error', function () {
    $directory = projectWith(['guardrails.php' => "<?php\n\nreturn [\n"]);

    expect(fn () => ConfigurationFile::read($directory))
        ->toThrow(InvalidArgumentException::class, "{$directory}/guardrails.php does not parse: Syntax error, unexpected EOF");
});

it('reads an instance with calls chained on it as an entry of its class', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

    return [
        (new FilesWithinTokenBudget(limit: 8000))->only('app')->allowing(['app/Legacy.php' => 'It predates the rule.']),
        (new NoEmDashes)?->only('docs'),
        NoEval::class,
    ];

    PHP]);

    $file = ConfigurationFile::read($directory);

    expect($file->classes())->toBe([FilesWithinTokenBudget::class, NoEmDashes::class, NoEval::class])
        ->and($file->configured())->toBe([FilesWithinTokenBudget::class, NoEmDashes::class]);
});

it('keeps an instance with calls chained on it as it is while its class is selected, and drops it whole with its import when it is not', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval;
    use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;

    return [
        (new FilesWithinTokenBudget(limit: 8000))
            ->only('app')
            ->allowing(['app/Legacy.php' => 'It predates the rule.']),
        NoEval::class,
    ];

    PHP]);

    $before = file_get_contents($directory.'/guardrails.php');

    ConfigurationFile::read($directory)->select([FilesWithinTokenBudget::class, NoEval::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe($before);

    ConfigurationFile::read($directory)->select([NoEval::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval;

    return [
        NoEval::class,
    ];

    PHP);
});

it('inserts a new entry before the only entry on a line of its own, under the comment above that entry', function () {
    $directory = projectWith(['guardrails.php' => <<<'PHP'
    <?php

    return [
        // Prose only.
        new \ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes,
    ];

    PHP]);

    ConfigurationFile::read($directory)->select([FilesWithinTokenBudget::class, NoEmDashes::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;

    return [
        FilesWithinTokenBudget::class,
        // Prose only.
        new \ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes,
    ];

    PHP);
});

it('prints the new entry one per line when it replaces every entry', function (string $return) {
    $directory = projectWith(['guardrails.php' => "<?php\n\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval;\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Text\\NoEmDashes;\n\n{$return}\n"]);

    ConfigurationFile::read($directory)->select([NoDebugCalls::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe(<<<'PHP'
    <?php

    use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;

    return [
        NoDebugCalls::class,
    ];

    PHP);
})->with([
    'two class entries' => "return [\n    NoEval::class,\n    NoEmDashes::class,\n];",
    'a class and an instance' => "return [\n    NoEval::class,\n    new NoEmDashes,\n];",
]);
