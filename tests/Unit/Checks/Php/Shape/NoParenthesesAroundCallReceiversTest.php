<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\NoParenthesesAroundCallReceivers;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a method call made on a call wrapped in parentheses', function (string $code) {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\n{$code};\n");

    expect($file->lines(new NoParenthesesAroundCallReceivers))->toBe([3]);
})->with([
    'a method call' => '($user->team())->role()',
    'a static call' => '(Role::make())->for($user)',
    'a function call' => '(foo())->bar()',
    'a nullsafe call' => '($a?->b())?->c()',
    'a nullsafe receiver and a plain call' => '($a?->b())->c()',
    'a call spaced inside the parentheses' => '( $user->team() )->role()',
    'a call assigned to a variable' => '$role = ($user->team())->role()',
    'a call inside a condition' => 'if (($user->team())->role()) {}',
]);

it('passes a call whose receiver needs its parentheses or has none', function (string $code) {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\n{$code};\n");

    expect($file->lines(new NoParenthesesAroundCallReceivers))->toBe([]);
})->with([
    'a new object' => '(new Foo)->bar()',
    'a new object assigned to a variable' => '$x = (new Carbon)->addDay()',
    'a new object with arguments' => '(new Foo($a))->bar()',
    'a method chain' => '$user->team()->role()',
    'a static chain' => 'Role::make()->for($user)',
    'a function call with a call as its argument' => 'foo($user->team())->bar()',
    'a group that changes the meaning' => '($a ?? $b)->c()',
    'a variable in parentheses' => '($user)->role()',
    'a call that is wrapped but not called' => '($user->team())->role',
]);

it('reads every PHP file', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, "<?php\n\n(\$user->team())->role();\n");

    expect($file->lines(new NoParenthesesAroundCallReceivers))->toBe([3]);
})->with([
    'application code' => 'app/Models/User.php',
    'a test' => 'tests/Unit/UserTest.php',
]);

it('fails a call on a wrapped call the change edits on a line of its own', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\n(\$user->team(\n    \$organisation,\n))->role();\n")
        ->changedFrom("<?php\n\n(\$user->team(\n    \$company,\n))->role();\n");

    expect($file->lines(new NoParenthesesAroundCallReceivers))->toBe([5]);
});

it('passes a wrapped call the change leaves alone', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\n(\$user->team())->role();\n\n\$name = 'Ada';\n")
        ->changedFrom("<?php\n\n(\$user->team())->role();\n\n\$name = 'Bob';\n");

    expect($file->lines(new NoParenthesesAroundCallReceivers))->toBe([]);
});
