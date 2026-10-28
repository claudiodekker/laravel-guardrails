<?php

use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('passes a monorepo whose root composer.json autoloads its packages, with every tracked file under every built-in check', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->copy(__DIR__.'/../Fixtures/monorepo')
        ->selectPreset('opinionated')
        ->stageAll()
        ->commitWithoutHook()
        ->runGuardrails('--all'));

    expect($result->errorOutput)->toBe('')
        ->and($result->successful())->toBeTrue()
        ->and($result->output)->toBe("Guardrails passed: 35 checks over 12 files (every tracked file).\n");
});

it('refuses a change to a monorepo that breaks a rule in each kind of code, by the kind of the package folder each file sits in', function () {
    $result = ScratchRepository::using(fn (ScratchRepository $repository) => $repository
        ->copy(__DIR__.'/../Fixtures/monorepo')
        ->selectPreset('opinionated')
        ->stageAll()
        ->commitWithoutHook()
        ->publish()
        ->write('packages/crm/src/Debug.php', "<?php\n\nnamespace Acme\\Crm;\n\nclass Debug\n{\n    /**\n     * Dump the contact.\n     */\n    public function dump(Contact \$contact): void\n    {\n        dd(\$contact);\n    }\n}\n")
        ->write('packages/billing/tests/Unit/HttpTest.php', "<?php\n\nnamespace Acme\\Billing\\Tests\\Unit;\n\nuse PHPUnit\\Framework\\TestCase;\n\nclass HttpTest extends TestCase\n{\n    public function test_it_loads_the_page(): void\n    {\n        \$this->get('/invoices');\n    }\n}\n")
        ->write('packages/billing/config/cache.php', "<?php\n\nreturn [\n    'ttl_seconds' => 2592000,\n];\n")
        ->write('packages/billing/database/migrations/2026_01_02_000000_add_total_to_invoices_table.php', "<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    /**\n     * Run the migrations.\n     */\n    public function up(): void\n    {\n        Schema::table('invoices', function (Blueprint \$table) {\n            \$table->float('total_price');\n        });\n    }\n};\n")
        ->write('packages/billing/routes/web.php', "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/invoices', fn () => dd('Invoices'));\n")
        ->write('README.md', "# Acme monorepo \u{2014} two packages\n")
        ->stageAll()
        ->runGuardrails('--staged'));

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toBe(<<<'TEXT'
        NoEmDashes
          README.md:1 Replace the em dash with a comma, a colon, parentheses or a new sentence.

        NoHttpInUnitTests
          packages/billing/tests/Unit/HttpTest.php:11 Move the test to tests/Feature, because a unit test runs without the HTTP kernel.

        NoDebugCalls
          packages/billing/routes/web.php:5 dd() is forbidden. Remove the debug call before committing.
          packages/crm/src/Debug.php:12 dd() is forbidden. Remove the debug call before committing.

        DurationsAsProducts
          packages/billing/config/cache.php:4 Write the duration as a product of its units, such as 12 * 60 * 60, so it reads as hours and minutes.

        NoFloatMoneyColumns
          packages/billing/database/migrations/2026_01_02_000000_add_total_to_invoices_table.php:15 Store money as an integer of its smallest unit, such as cents, in an integer column.

        TEXT);
});
