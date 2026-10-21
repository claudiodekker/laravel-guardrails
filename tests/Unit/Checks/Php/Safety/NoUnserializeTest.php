<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoUnserialize;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails unserialize() unless it passes allowed_classes', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, <<<'PHP'
        <?php

        $a = unserialize($payload);
        $b = \unserialize($payload, ['max_depth' => 2]);
        $c = unserialize($payload, ['allowed_classes' => false]);
        $d = unserialize($payload, ['allowed_classes' => [Order::class]]);
        $e = unserialize($payload, options: ['allowed_classes' => false]);
        $f = json_decode($payload);
        $g = $this->unserialize($payload);
        PHP);

    expect($file->lines(new NoUnserialize))->toBe([3, 4]);
})->with([
    'application code' => 'app/Jobs/Import.php',
    'a test' => 'tests/Unit/ImportTest.php',
]);

it('fails unserialize() whose options the change edits on a line of its own', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Jobs/Import.php', "<?php\n\nunserialize(\n    \$payload,\n    ['max_depth' => 2],\n);\n")
        ->changedFrom("<?php\n\nunserialize(\n    \$payload,\n    ['allowed_classes' => false],\n);\n");

    expect($file->lines(new NoUnserialize))->toBe([3]);
});
