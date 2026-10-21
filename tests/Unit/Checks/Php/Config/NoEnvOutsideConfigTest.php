<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Config\NoEnvOutsideConfig;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails env() and Env::get() in application code', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Mailer.php', <<<'PHP'
        <?php

        namespace App\Services;

        use Illuminate\Support\Env;

        class Mailer
        {
            public function send(): void
            {
                $key = env('MAIL_KEY');
                $host = \env('MAIL_HOST', 'localhost');
                $port = Env::get('MAIL_PORT');
                $user = \Illuminate\Support\Env::get('MAIL_USER');
                $name = config('mail.from.name');
                $this->env('MAIL_ENV');
                Env::enablePutenv();
            }
        }
        PHP);

    expect($file->lines(new NoEnvOutsideConfig))->toBe([11, 12, 13, 14]);
});

it('leaves the config alone, and any file in a config folder, and reads the rest', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\nreturn ['key' => env('APP_KEY')];\n");

    expect($file->lines(new NoEnvOutsideConfig))->toBe($lines);
})->with([
    'a config file' => ['config/app.php', Kind::CONFIG, []],
    'a nested config file' => ['config/services/stripe.php', Kind::CONFIG, []],
    'the config of a package in the repository' => ['packages/billing/config/billing.php', Kind::CONFIG, []],
    'the config of a fixture app in the tests' => ['tests/Fixtures/skeleton/config/app.php', Kind::TESTS, []],
    'a file in a configs folder' => ['docs/configs/app.php', Kind::TEXT, [3]],
    'a route file' => ['routes/web.php', Kind::ROUTES, [3]],
    'the bootstrap' => ['bootstrap/app.php', Kind::TEXT, [3]],
    'source code' => ['app/Support/Mail.php', Kind::SOURCE, [3]],
    'a test' => ['tests/Feature/MailTest.php', Kind::TESTS, [3]],
]);

it('passes env() whose argument the change edits on another line', function () {
    $file = TestFile::of(Kind::SOURCE, 'app/Support/Mail.php', "<?php\n\nenv(\n    'MAIL_HOST',\n);\n")
        ->changedFrom("<?php\n\nenv(\n    'MAIL_PORT',\n);\n");

    expect($file->lines(new NoEnvOutsideConfig))->toBe([]);
});
