<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;
use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('runs only the checks guardrails.php selects, built in or the project\'s own, in that order, with the options it gives them', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', <<<'PHP'
        <?php

        use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\OneClassPerFile;
        use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
        use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;

        return [
            new EveryLine(null, ['*.md']),
            new FilesWithinTokenBudget(limit: 1000),
            OneClassPerFile::class,
        ];

        PHP)
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->writeBytes('docs/guide.md', 4001)
        ->write('app/Models/User.php', "<?php\n\nclass User {}\n\nclass Team {}\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toStartWith("EveryLine\n  README.md:1 Remove the line.\n  docs/guide.md:1 Remove the line.\n\nFilesWithinTokenBudget\n  docs/guide.md Split the file into files under 1000 tokens (4000 bytes) each.\n")
        ->and($result->errorOutput)->toEndWith("\nOneClassPerFile\n  app/Models/User.php:5 Move the class into its own file, named after it.\n");
});

it('reports two checks of one class name from different namespaces under their full names', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', <<<'PHP'
        <?php

        use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;
        use ClaudioDekker\LaravelGuardrails\Tests\Support\Nested\EveryLine as NestedEveryLine;

        return [
            new EveryLine(null, ['*.md']),
            new NestedEveryLine(null, ['*.md']),
        ];

        PHP)
        ->write('README.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine
          README.md:1 Remove the line.

        ClaudioDekker\LaravelGuardrails\Tests\Support\Nested\EveryLine
          README.md:1 Remove the line.

        TEXT);
});

it('heads the failures with the class name of the check, for an instance too', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', "<?php\n\nreturn [\n    new \\ClaudioDekker\\LaravelGuardrails\\Tests\\Support\\OnlyLine(1),\n];\n")
        ->write('README.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        OnlyLine
          README.md:1 Remove line 1.
          guardrails.php:1 Remove line 1.

        TEXT);
});

it('runs the rule classes of the project listed in guardrails.php, under their class names', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', <<<'PHP'
        <?php

        use ClaudioDekker\LaravelGuardrails\Tests\Support\NoContainerLookups;

        return [
            NoContainerLookups::class,
        ];

        PHP)
        ->write('app/Services/Prune.php', "<?php\n\nnamespace App\\Services;\n\nclass Prune\n{\n    public function handle(): void\n    {\n        app('cache')->forget('users');\n    }\n}\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoContainerLookups
          app/Services/Prune.php:9 app() is forbidden. Inject the service through the constructor instead.

        TEXT);
});

it('runs each configured instance of a check over its own paths, reporting each under the name as() gives it, in the order they are listed', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('composer.json', json_encode(['name' => 'acme/app', 'autoload' => ['psr-4' => ['Acme\\Billing\\' => 'packages/billing/src/', 'Acme\\Crm\\' => 'packages/crm/src/']]], JSON_THROW_ON_ERROR))
        ->write('guardrails.php', <<<'PHP'
        <?php

        use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\NoNamespaces;

        return [
            (new NoNamespaces(['Acme\Billing']))->only('packages/crm/src')->as('NoBillingInCrm'),
            (new NoNamespaces(['Acme\Crm'], message: 'Go through the events billing listens to.'))->only('packages/billing/src')->as('NoCrmInBilling'),
        ];

        PHP)
        ->write('packages/crm/src/Contact.php', "<?php\n\nnamespace Acme\\Crm;\n\nuse Acme\\Billing\\Invoice;\n\nclass Contact\n{\n    public ?Invoice \$invoice = null;\n}\n")
        ->write('packages/billing/src/Invoice.php', "<?php\n\nnamespace Acme\\Billing;\n\nuse Acme\\Crm\\Contact;\n\nclass Invoice\n{\n    public ?Contact \$contact = null;\n}\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoBillingInCrm
          packages/crm/src/Contact.php:5 Remove the dependency on Acme\Billing, which this code may not use.
          packages/crm/src/Contact.php:9 Remove the dependency on Acme\Billing, which this code may not use.

        NoCrmInBilling
          packages/billing/src/Invoice.php:5 Go through the events billing listens to.
          packages/billing/src/Invoice.php:9 Go through the events billing listens to.

        TEXT);
});

it('keeps the name as() gives a check, and prints a check of another class whose name it takes under its full name', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', <<<'PHP'
        <?php

        use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;
        use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;

        return [
            NoEmDashes::class,
            (new EveryLine(null, ['*.md']))->as('NoEmDashes'),
        ];

        PHP)
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes
          README.md:1 Replace the em dash with a comma, a colon, parentheses or a new sentence.

        NoEmDashes
          README.md:1 Remove the line.

        TEXT);
});

it('refuses an anonymous check that as() does not name, and runs one it names under that name', function () {
    $run = fn (string $entry) => ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', "<?php\n\nreturn [\n    {$entry},\n];\n")
        ->write('app/Dyn.php', "<?php\n\neval(\$code);\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    $unnamed = $run('new class extends \\ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval {}');
    $named = $run("(new class extends \\ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval {})->as('NoEvalInApp')");

    expect($unnamed->exitCode)->toBe(2)
        ->and($unnamed->errorOutput)->toEndWith("/guardrails.php: entry 1 is an anonymous class. Name it with ->as('...').\n")
        ->and($named->exitCode)->toBe(1)
        ->and($named->errorOutput)->toStartWith("NoEvalInApp\n  app/Dyn.php:3 Replace eval()");
});

it('refuses two instances of one check that as() does not tell apart, naming the entries', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', <<<'PHP'
        <?php

        use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\NoNamespaces;
        use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

        return [
            NoEmDashes::class,
            (new NoNamespaces(['Acme\Billing']))->only('packages/crm/src'),
            (new NoNamespaces(['Acme\Crm']))->only('packages/billing/src'),
        ];

        PHP)
        ->write('README.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe("guardrails.php: checks 2 and 3 are both labelled NoNamespaces. Name one with ->as('...').\n");
});

it('refuses NoSyntaxErrors limited to some paths, so no file that does not parse slips past the run', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', "<?php\n\nreturn [\n    (new \\ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\NoSyntaxErrors)->only('tests'),\n];\n")
        ->write('app/Broken.php', "<?php\n\nclass Broken {\n")
        ->stageAll()
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe("the staged guardrails.php could not be loaded: NoSyntaxErrors runs over every PHP file, so it takes no only(). Allow the files that are broken on purpose with allowing() instead.\n");
});

it('refuses a files() pattern that names a folder once, before it reads any file', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', "<?php\n\nreturn [\n    new \\ClaudioDekker\\LaravelGuardrails\\Tests\\Support\\EveryLine(null, ['*.md', 'docs/*.md']),\n];\n")
        ->write('README.md', "Clean.\n")
        ->write('docs/guide.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe("ClaudioDekker\\LaravelGuardrails\\Tests\\Support\\EveryLine: files() takes patterns on the file's name, such as *.php. docs/*.md names a folder: declare the kinds with kinds() and limit the paths with only() in guardrails.php.\n");
});

it('names an anonymous check plainly when its files() pattern names a folder', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', <<<'PHP'
        <?php

        use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;

        return [
            (new class extends EveryLine
            {
                protected function files(): array
                {
                    return ['docs/*.md'];
                }
            })->as('EveryDoc'),
        ];

        PHP)
        ->write('README.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(2)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toStartWith(EveryLine::class.'@anonymous: files() takes patterns')
        ->and($result->errorOutput)->not->toContain("\0");
});

it('reports a check that gives no message as a crash', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', "<?php\n\nreturn [\n    \\ClaudioDekker\\LaravelGuardrails\\Tests\\Support\\Silent::class,\n];\n")
        ->write('README.md', "Clean.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    $at = dirname(__DIR__, 2).'/src/Check.php';
    $message = 'Silent gives no message. Return one from message(), or pass one to each Violation. Fix the check, or allow the file.';

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toMatch('/^Silent\n  README\.md The check crashed at '.preg_quote($at, '/').':\d+: '.preg_quote($message, '/').'\n$/');
});

it('reports a check that crashes under its name, naming the file, where the check crashed and the error, runs the other checks, and exits with two', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', <<<'PHP'
        <?php

        use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;
        use ClaudioDekker\LaravelGuardrails\Tests\Support\Crashing;

        return [
            Crashing::class,
            NoEmDashes::class,
        ];

        PHP)
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    $at = dirname(__DIR__).'/Support/Crashing.php';

    expect($result->exitCode)->toBe(2)
        ->and($result->errorOutput)->toMatch('/^Crashing\n  README\.md The check crashed at '.preg_quote($at, '/').':\d+: The disk is full\. Fix the check, or allow the file\.\n\nNoEmDashes\n  README\.md:1 Replace the em dash with a comma, a colon, parentheses or a new sentence\.\n$/');
});

it('reports a crashing check on a few files one by one, then counts the rest on one line', function () {
    $result = ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->write('guardrails.php', "<?php\n\nreturn [\n    \\ClaudioDekker\\LaravelGuardrails\\Tests\\Support\\Crashing::class,\n];\n");

        foreach (range(1, 5) as $number) {
            $repository->write("docs/page-{$number}.md", "Page {$number}\n");
        }

        return $repository->stageAll()->commitWithoutHook()->runGuardrails('--all');
    });

    expect($result->exitCode)->toBe(2)
        ->and(substr_count($result->errorOutput, 'The check crashed at'))->toBe(3)
        ->and($result->errorOutput)->toEndWith("  The check crashed on 2 more files too. Fix the check.\n");
});
