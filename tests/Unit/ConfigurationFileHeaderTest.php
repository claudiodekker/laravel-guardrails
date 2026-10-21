<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Comments\SlopedComments;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoUnserialize;
use ClaudioDekker\LaravelGuardrails\ConfigurationFile;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('starts a fresh file with a header that holds every paragraph to the three-line slope', function () {
    $directory = projectWith([]);

    ConfigurationFile::read($directory)->select([NoEval::class]);

    $file = TestFile::of(Kind::CONFIG, 'guardrails.php', (string) file_get_contents($directory.'/guardrails.php'));

    expect($file->lines(new SlopedComments(banners: true)))->toBe([]);
});

it('keeps the header and the imports below it as they are while entries come and go', function () {
    $directory = projectWith([]);
    $path = $directory.'/guardrails.php';

    ConfigurationFile::read($directory)->select([NoDebugCalls::class]);
    $header = substr((string) file_get_contents($path), 0, (int) strpos((string) file_get_contents($path), "\nuse ") + 1);

    ConfigurationFile::read($directory)->select([NoDebugCalls::class, NoEval::class]);
    $added = file_get_contents($path);

    ConfigurationFile::read($directory)->select([NoDebugCalls::class, NoEval::class]);
    $rerun = file_get_contents($path);

    ConfigurationFile::read($directory)->select([NoEval::class]);
    $removed = file_get_contents($path);

    ConfigurationFile::read($directory)->select([]);
    $emptied = file_get_contents($path);

    ConfigurationFile::read($directory)->select([NoUnserialize::class]);
    $refilled = (string) file_get_contents($path);

    expect($added)->toStartWith($header.'use '.NoDebugCalls::class)
        ->and($rerun)->toBe($added)
        ->and($removed)->toStartWith($header.'use '.NoEval::class)
        ->and($emptied)->toBe($header."return [];\n")
        ->and($refilled)->toStartWith($header.'use '.NoUnserialize::class)
        ->and(substr_count($refilled, '| Laravel Guardrails Checks'))->toBe(1);
});

it('keeps the header of a file whatever it says, byte for byte, and adds none to a file without one', function (string $header) {
    $directory = projectWith(['guardrails.php' => "<?php\n\n{$header}use ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoDebugCalls;\n\nreturn [\n    NoDebugCalls::class,\n];\n"]);

    ConfigurationFile::read($directory)->select([NoDebugCalls::class, NoEval::class]);
    ConfigurationFile::read($directory)->select([NoEval::class]);
    ConfigurationFile::read($directory)->select([NoEval::class, NoUnserialize::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe("<?php\n\n{$header}use ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval;\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoUnserialize;\n\nreturn [\n    NoEval::class,\n    NoUnserialize::class,\n];\n");
})->with([
    'a banner of the team' => "/*\n|--------------------------------------------------------------------------\n| House rules\n|--------------------------------------------------------------------------\n*/\n\n",
    'a line comment' => "// Maintained by the platform team.\n\n",
    'no header' => '',
]);

it('adds the imports below a header that is not attached to the return', function () {
    $directory = projectWith(['guardrails.php' => "<?php\n\n/*\n| House rules\n*/\n\nreturn [\n];\n"]);

    ConfigurationFile::read($directory)->select([NoEval::class]);

    expect(file_get_contents($directory.'/guardrails.php'))->toBe("<?php\n\n/*\n| House rules\n*/\n\nuse ClaudioDekker\\LaravelGuardrails\\Checks\\Php\\Safety\\NoEval;\n\nreturn [\n    NoEval::class,\n];\n");
});
