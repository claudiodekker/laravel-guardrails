<?php

use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails an em dash in any kind of file', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, "Fast, \u{2014} and loose.\n\nNo dash here.\nOne \u{2014} two \u{2014} three.\n");

    expect($file->lines(new NoEmDashes))->toBe([1, 4]);
})->with([
    'README.md',
    'app/Models/User.php',
    'composer.json',
    '.github/workflows/tests.yml',
    'resources/views/welcome.blade.php',
]);

it('passes the dashes that are not em dashes', function () {
    $file = TestFile::of(Kind::TEXT, 'README.md', "A hyphen - and an en dash \u{2013} and two hyphens -- are fine.\n");

    expect($file->lines(new NoEmDashes))->toBe([]);
});
