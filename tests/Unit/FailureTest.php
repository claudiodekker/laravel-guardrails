<?php

use ClaudioDekker\LaravelGuardrails\Failure;

it('ends the message of the error with a period before it names the fix', function (string $message) {
    $failure = Failure::crash('Crashing', 'README.md', new RuntimeException($message), dirname(__DIR__, 2));

    expect($failure->message)->toEndWith(': The disk is full. Fix the check, or allow the file.');
})->with([
    'without a period' => ['The disk is full'],
    'with a period' => ['The disk is full.'],
]);

it('shows where it crashed from the project when the file is inside it', function () {
    $failure = Failure::crash('Crashing', 'README.md', new RuntimeException('Broken.'), dirname(__DIR__, 2));
    $outside = Failure::crash('Crashing', 'README.md', new RuntimeException('Broken.'), '/elsewhere');

    expect($failure->message)->toStartWith('The check crashed at tests/Unit/FailureTest.php:')
        ->and($outside->message)->toStartWith('The check crashed at '.__FILE__.':');
});
