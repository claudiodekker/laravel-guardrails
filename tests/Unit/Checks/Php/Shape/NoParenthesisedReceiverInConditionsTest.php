<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\NoParenthesisedReceiverInConditions;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a condition or subject that calls a method on a parenthesised value', function (string $code) {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\n{$code}\n");

    expect($file->lines(new NoParenthesisedReceiverInConditions))->toBe([3]);
})->with([
    'a match on a new object' => "\$label = match ((new Foo)->bar()) {\n    default => 'other',\n};",
    'a match on a wrapped call' => "\$label = match ((\$user->team())->role()) {\n    default => 'other',\n};",
    'a switch' => "switch ((new Foo)->bar()) {\n    default:\n}",
    'an if' => 'if ((new Foo)->ok()) {}',
    'a while' => 'while ((new Queue)->next()) {}',
    'a nullsafe call' => 'if ((new Foo)?->ok()) {}',
    'a longer chain' => 'if ((new Foo)->bar()->ok()) {}',
    'a group with more after it' => 'if (($user->team())->role() === $role) {}',
]);

it('fails an elseif that calls a method on a parenthesised value', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\nif (\$ready) {\n} elseif ((new Foo)->ok()) {\n}\n");

    expect($file->lines(new NoParenthesisedReceiverInConditions))->toBe([4]);
});

it('passes a condition or subject without a parenthesised receiver', function (string $code) {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\n{$code}\n");

    expect($file->lines(new NoParenthesisedReceiverInConditions))->toBe([]);
})->with([
    'a group with nothing called on it' => 'if (($a && $b) || $c) {}',
    'a match on a plain chain' => "\$label = match (\$user->team()->role()) {\n    default => 'other',\n};",
    'a match on a new object without the parentheses' => "\$label = match (new Foo()->bar()) {\n    default => 'other',\n};",
    'a call outside a condition' => '$x = (new Foo)->bar();',
    'a named value' => "\$role = \$user->role();\n\nif (\$role) {}",
    'a call that takes a parenthesised argument' => 'if (foo((new Foo)->bar())) {}',
    'a while on a plain call' => 'while ($queue->next()) {}',
    'a parenthesised call that does not open the condition' => 'if ($ready && (new Foo)->ok()) {}',
]);

it('reads every PHP file', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, "<?php\n\nif ((new Foo)->ok()) {}\n");

    expect($file->lines(new NoParenthesisedReceiverInConditions))->toBe([3]);
})->with([
    'application code' => 'app/Models/User.php',
    'a test' => 'tests/Unit/UserTest.php',
]);

it('fails a condition the change edits on a line of its own', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\nif ((new Foo)->ok(\n    \$team,\n)) {}\n")
        ->changedFrom("<?php\n\nif ((new Foo)->ok(\n    \$company,\n)) {}\n");

    expect($file->lines(new NoParenthesisedReceiverInConditions))->toBe([3]);
});

it('passes a condition the change leaves alone', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Models/User.php', "<?php\n\nif ((new Foo)->ok()) {}\n\n\$name = 'Ada';\n")
        ->changedFrom("<?php\n\nif ((new Foo)->ok()) {}\n\n\$name = 'Bob';\n");

    expect($file->lines(new NoParenthesisedReceiverInConditions))->toBe([]);
});
