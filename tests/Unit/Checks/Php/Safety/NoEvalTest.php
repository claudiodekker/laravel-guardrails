<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails every eval() call', function (string $path) {
    $file = TestFile::of(Kind::TEXT, $path, <<<'PHP'
        <?php

        $result = eval('return 1;');
        $evaluate = fn () => eval($code);
        $this->eval($code);
        $name = 'eval';
        PHP);

    expect($file->lines(new NoEval))->toBe([3, 4]);
})->with([
    'application code' => 'app/Services/Calculator.php',
    'a test' => 'tests/Unit/CalculatorTest.php',
    'a script' => 'scripts/build.php',
]);

it('fails eval() whose argument the change edits on a line of its own', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Calculator.php', "<?php\n\neval(\n    \$code\n);\n")
        ->changedFrom("<?php\n\neval(\n    \$formula\n);\n");

    expect($file->lines(new NoEval))->toBe([3]);
});
