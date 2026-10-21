<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('fails a staged deletion under a check that judges deletions, and passes it under the checks that do not', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', keepMigrations())
        ->write('database/migrations/2026_01_01_000000_create_users_table.php', "<?php\n\nreturn 1;\n")
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->stageDeletion('database/migrations/2026_01_01_000000_create_users_table.php')
        ->stageDeletion('README.md')
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->output)->toBe('')
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        KeepMigrations
          database/migrations/2026_01_01_000000_create_users_table.php Add a new migration instead, because this one may already have run.

        TEXT);
});

it('passes a staged deletion under the checks that do not judge deletions, counting the file it deletes', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->select()
        ->write('README.md', "Fast \u{2014} and loose.\n")
        ->stageAll()
        ->commitWithoutHook()
        ->stageDeletion('README.md')
        ->runGuardrails('--staged'));

    expect($result->errorOutput)->toBe('')
        ->and($result->output)->toBe("Guardrails passed: 7 checks over 1 file (staged changes).\n");
});

it('fails a deletion since the base under a check that judges deletions', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', keepMigrations())
        ->write('database/migrations/2026_01_01_000000_create_users_table.php', "<?php\n\nreturn 1;\n")
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->stageDeletion('database/migrations/2026_01_01_000000_create_users_table.php')
        ->commitWithoutHook()
        ->runGuardrails());

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        KeepMigrations
          database/migrations/2026_01_01_000000_create_users_table.php Add a new migration instead, because this one may already have run.

        TEXT);
});
