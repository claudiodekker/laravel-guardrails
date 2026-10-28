<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\NoMatchOnCall;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a match whose subject is built inline', function (string $subject) {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\n\$label = match ({$subject}) {\n    default => 'other',\n};\n");

    expect($file->lines(new NoMatchOnCall))->toBe([3]);
})->with([
    'a call on a new object' => '(new Roles)->for($user)',
    'a nullsafe call on a new object' => '(new Roles)?->for($user)',
    'a static call on a new object' => '(new Roles)::for($user)',
    'a new object' => 'new Role',
    'a chain on a new object' => '(new Roles)->for($user)->first()',
    'a call on a parenthesized method call' => '($user->team())->role()',
    'a call on a parenthesized static call' => '(Role::make())->for($user)',
    'a call on a parenthesized call inside a chain' => '($user->team())->owner()->role()',
]);

it('fails a switch whose subject is built inline', function (string $subject) {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\nswitch ({$subject}) {\n    default:\n        break;\n}\n");

    expect($file->lines(new NoMatchOnCall))->toBe([3]);
})->with([
    'a call on a new object' => '(new Roles)->for($user)',
    'a new object' => 'new Role',
    'a chain on a new object' => '(new Roles)->for($user)->first()',
    'a call on a parenthesized method call' => '($user->team())->role()',
    'a call on a parenthesized static call' => '(Role::make())->for($user)',
]);

it('passes a match on a call or a chain of calls', function (string $subject) {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\n\$label = match ({$subject}) {\n    default => 'other',\n};\n");

    expect($file->lines(new NoMatchOnCall))->toBe([]);
})->with([
    'a function call' => 'lookup()',
    'a function call with an argument' => 'strtolower($role)',
    'a method call' => '$user->role()',
    'a method call on this' => '$this->mode()',
    'a method call on a property' => '$this->settings->mode()',
    'a static call' => 'Role::for($user)',
    'a static from call' => 'Role::from($value)',
    'a nullsafe call' => '$user?->role()',
    'a chain of method calls' => '$user->team()->role()',
    'a nullsafe chain' => '$user?->team()?->role()',
    'a method call on a static call' => 'Role::make()->for($user)',
    'a method call on a function call' => 'lookup()->role()',
    'a call that builds an object in an argument' => '$roles->for(new Team)',
    'a call with parentheses in an argument' => '$roles->for(($team))',
]);

it('passes a switch on a call or a chain of calls', function (string $subject) {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\nswitch ({$subject}) {\n    default:\n        break;\n}\n");

    expect($file->lines(new NoMatchOnCall))->toBe([]);
})->with([
    'a method call' => '$user->role()',
    'a chain of method calls' => '$user->team()->role()',
]);

it('passes a match whose subject is already named', function (string $subject) {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\n\$label = match ({$subject}) {\n    default => 'other',\n};\n");

    expect($file->lines(new NoMatchOnCall))->toBe([]);
})->with([
    'a variable' => '$role',
    'true' => 'true',
    'a literal' => "'admin'",
    'a property' => '$user->role',
    'a class name' => '$user::class',
    'a constant' => 'Role::Admin',
]);

it('reads every PHP file', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, "<?php\n\n\$label = match ((new Roles)->for(\$user)) {\n    default => 'other',\n};\n");

    expect($file->lines(new NoMatchOnCall))->toBe([3]);
})->with([
    'application code' => 'app/Models/User.php',
    'a test' => 'tests/Unit/UserTest.php',
]);

it('fails a match whose subject call the change edits on a line of its own', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\n\$label = match ((new Roles)->for(\n    \$team,\n)) {\n    default => 'other',\n};\n")
        ->changedFrom("<?php\n\n\$label = match ((new Roles)->for(\n    \$organisation,\n)) {\n    default => 'other',\n};\n");

    expect($file->lines(new NoMatchOnCall))->toBe([3]);
});
