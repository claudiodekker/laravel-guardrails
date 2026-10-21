<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\NoMatchOnCall;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a match whose subject is a call', function (string $subject) {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\n\$label = match ({$subject}) {\n    default => 'other',\n};\n");

    expect($file->lines(new NoMatchOnCall))->toBe([3]);
})->with([
    'a function call' => 'lookup()',
    'a method call' => '$user->role()',
    'a static call' => 'Role::for($user)',
    'a call on a new object' => '(new Roles)->for($user)',
    'a nullsafe call' => '$user?->role()',
    'a new object' => 'new Role',
]);

it('passes a match whose subject is already named', function (string $subject) {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\n\$label = match ({$subject}) {\n    default => 'other',\n};\n");

    expect($file->lines(new NoMatchOnCall))->toBe([]);
})->with([
    'a variable' => '$role',
    'true' => 'true',
    'a property' => '$user->role',
    'a class name' => '$user::class',
    'a constant' => 'Role::Admin',
]);

it('reads every PHP file', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, "<?php\n\n\$label = match (\$user->role()) {\n    default => 'other',\n};\n");

    expect($file->lines(new NoMatchOnCall))->toBe([3]);
})->with([
    'application code' => 'app/Models/User.php',
    'a test' => 'tests/Unit/UserTest.php',
]);

it('fails a match whose subject call the change edits on a line of its own', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\n\$label = match (\$user->role(\n    \$team,\n)) {\n    default => 'other',\n};\n")
        ->changedFrom("<?php\n\n\$label = match (\$user->role(\n    \$organisation,\n)) {\n    default => 'other',\n};\n");

    expect($file->lines(new NoMatchOnCall))->toBe([3]);
});
