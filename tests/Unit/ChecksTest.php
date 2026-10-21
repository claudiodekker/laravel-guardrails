<?php

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\NoSyntaxErrors;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\NoNamespaces;

dataset('checks', function () {
    $root = __DIR__.'/../../src/Checks';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $checks = [];

    foreach ($files as $file) {
        $check = str_replace('/', '\\', substr($file->getPathname(), strlen($root) + 1, -strlen('.php')));

        if (is_subclass_of("ClaudioDekker\\LaravelGuardrails\\Checks\\{$check}", Check::class)) {
            $checks[$check] = [$check];
        }
    }

    ksort($checks);

    return $checks;
});

it('has a sample test for every check', function (string $check) {
    expect(__DIR__.'/Checks/'.str_replace('\\', '/', $check).'Test.php')->toBeFile();
})->with('checks');

it('describes its rule and the files it reads on one line each', function (string $check) {
    /** @var class-string<Check> $class */
    $class = "ClaudioDekker\\LaravelGuardrails\\Checks\\{$check}";
    $instance = $class === NoNamespaces::class ? new NoNamespaces(['Acme\Billing']) : new $class;

    expect($instance->description())->toMatch('/^[^\n]+$/')
        ->and($instance->scope())->toMatch('/^[^\n]+$/');
})->with('checks');

it('explains its rule in more than its description', function (string $check) {
    /** @var class-string<Check> $class */
    $class = "ClaudioDekker\\LaravelGuardrails\\Checks\\{$check}";
    $instance = $class === NoNamespaces::class ? new NoNamespaces(['Acme\Billing']) : new $class;

    expect($instance->explanation())->not->toBe($instance->description())
        ->and($instance->explanation())->toMatch('/^[^\n]+$/');
})->with('checks');

it('is offered as a built-in check unless it does nothing until it is configured, or runs whether or not it is selected', function (string $check) {
    $manifest = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
    /** @var class-string<Check> $class */
    $class = "ClaudioDekker\\LaravelGuardrails\\Checks\\{$check}";
    $configuredOnly = (new ReflectionClass($class))->getConstructor()?->getNumberOfRequiredParameters() > 0;

    expect(in_array($class, $manifest['extra']['guardrails']['provides']['checks'], true))->toBe(! $configuredOnly && $class !== NoSyntaxErrors::class);
})->with('checks');

it('describes the files it reads itself when it narrows them past its kinds and patterns', function (string $check) {
    /** @var class-string<Check> $class */
    $class = "ClaudioDekker\\LaravelGuardrails\\Checks\\{$check}";
    $narrows = (new ReflectionMethod($class, 'reads'))->getDeclaringClass()->getName() === $class;
    $describes = (new ReflectionMethod($class, 'scope'))->getDeclaringClass()->getName() === $class;

    expect($describes || ! $narrows)->toBeTrue("{$check} overrides reads() without overriding scope(), so the picker and the list misdescribe what it reads.");
})->with('checks');
