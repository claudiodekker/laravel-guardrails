<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('still runs NoMatchOnCall for a project whose guardrails.php lists it', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->write('guardrails.php', <<<'PHP'
        <?php

        use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\NoMatchOnCall;

        return [
            NoMatchOnCall::class,
        ];

        PHP)
        ->write('app/Models/User.php', "<?php\n\n\$label = match (\$user->role()) {\n    default => 'other',\n};\n")
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe("NoMatchOnCall\n  app/Models/User.php:3 Name the call's result in a variable, then match on the variable.\n");
});
