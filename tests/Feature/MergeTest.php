<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('fails the resolution of a merge that leaves the config keys both branches added at one spot out of order', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Config\\SortedConfigKeys'])
        ->write('config/services.php', "<?php\n\nreturn [\n    'a' => 1,\n    'd' => 1,\n];\n")
        ->stageAll()
        ->commitWithoutHook()
        ->branch('feature')
        ->write('config/services.php', "<?php\n\nreturn [\n    'a' => 1,\n    'b' => 1,\n    'd' => 1,\n];\n")
        ->stageAll()
        ->commitWithoutHook()
        ->checkout('feature')
        ->write('config/services.php', "<?php\n\nreturn [\n    'a' => 1,\n    'c' => 1,\n    'd' => 1,\n];\n")
        ->stageAll()
        ->commitWithoutHook()
        ->checkout('main')
        ->merge('feature')
        ->write('config/services.php', "<?php\n\nreturn [\n    'a' => 1,\n    'c' => 1,\n    'b' => 1,\n    'd' => 1,\n];\n")
        ->stage('config/services.php')
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        SortedConfigKeys
          config/services.php:6 Move 'b' above 'c'.

        TEXT);
});

it('judges only what an octopus merge adds, passing what each merged branch committed', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}}')
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval'])
        ->write('app/Base.php', "<?php\n\nclass Base {}\n")
        ->stageAll()
        ->commitWithoutHook()
        ->branch('first')
        ->branch('second')
        ->checkout('first')
        ->write('app/First.php', "<?php\n\neval('1');\n")
        ->stageAll()
        ->commitWithoutHook()
        ->checkout('second')
        ->write('app/Second.php', "<?php\n\neval('2');\n")
        ->stageAll()
        ->commitWithoutHook()
        ->checkout('main')
        ->merge('first', 'second')
        ->write('app/Base.php', "<?php\n\nclass Base {}\n\neval('3');\n")
        ->stage('app/Base.php')
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEval
          app/Base.php:5 Replace eval() with code that does the same, because it runs whatever string it is given.

        TEXT);
});

it('passes the resolution of a merge that keeps a deletion one parent already made', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', keepMigrations())
        ->write('database/migrations/2026_01_01_000000_create_users_table.php', "<?php\n\nreturn 1;\n")
        ->stageAll()
        ->commitWithoutHook()
        ->branch('feature')
        ->stageDeletion('database/migrations/2026_01_01_000000_create_users_table.php')
        ->commitWithoutHook()
        ->checkout('feature')
        ->write('database/migrations/2026_01_01_000000_create_users_table.php', "<?php\n\nreturn 2;\n")
        ->stageAll()
        ->commitWithoutHook()
        ->checkout('main')
        ->merge('feature')
        ->stageDeletion('database/migrations/2026_01_01_000000_create_users_table.php')
        ->runGuardrails('--staged'));

    expect($result->errorOutput)->toBe('')
        ->and($result->exitCode)->toBe(0);
});

it('judges a merge that renames a file on one side and edits it on the other against what each side added', function () {
    $code = "<?php\n\nclass Invoice\n{\n    public function total(): int\n    {\n        return 1;\n    }\n\n    public function tax(): int\n    {\n        return 2;\n    }\n\n    public function net(): int\n    {\n        return 3;\n    }\n}\n";

    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('composer.json', '{"autoload": {"psr-4": {"App\\\\": "app/"}}}')
        ->select(['ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval'])
        ->write('app/Old.php', $code)
        ->stageAll()
        ->commitWithoutHook()
        ->branch('feature')
        ->move('app/Old.php', 'app/Invoice.php')
        ->commitWithoutHook()
        ->checkout('feature')
        ->write('app/Old.php', str_replace('return 3;', "return eval('3');", $code))
        ->stageAll()
        ->commitWithoutHook()
        ->checkout('main')
        ->merge('feature')
        ->write('app/Invoice.php', str_replace('return 2;', "return eval('2');", (string) file_get_contents($repository->path.'/app/Invoice.php')))
        ->stage('app/Invoice.php')
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEval
          app/Invoice.php:12 Replace eval() with code that does the same, because it runs whatever string it is given.

        TEXT);
});

it('does not judge a migration one parent deleted and the merge keeps as a deletion', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', keepMigrations())
        ->write('database/migrations/2026_01_01_000000_create_users_table.php', "<?php\n\nreturn 1;\n")
        ->stageAll()
        ->commitWithoutHook()
        ->branch('feature')
        ->stageDeletion('database/migrations/2026_01_01_000000_create_users_table.php')
        ->commitWithoutHook()
        ->checkout('feature')
        ->write('README.md', "Other work.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->checkout('main')
        ->merge('feature')
        ->write('database/migrations/2026_01_01_000000_create_users_table.php', "<?php\n\nreturn 1;\n")
        ->stageAll()
        ->runGuardrails('--staged'));

    expect($result->errorOutput)->toBe('')
        ->and($result->exitCode)->toBe(0);
});
