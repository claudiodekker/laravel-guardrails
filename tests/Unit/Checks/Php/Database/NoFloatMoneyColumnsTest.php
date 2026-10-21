<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Database\NoFloatMoneyColumns;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('fails a float or double column whose name means money', function () {
    $file = TestFile::of(Kind::MIGRATIONS, 'database/migrations/2026_01_01_000000_create_orders_table.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('orders', function (Blueprint $table) {
                    $table->float('price');
                    $table->double('total_amount');
                    $table->float('shipping_cost', 8, 2);
                    $table->double('service_fee')->nullable();
                    $table->float('Balance');
                    $table->float('weight');
                    $table->double('latitude');
                    $table->integer('price_in_cents');
                    $table->decimal('amount', 10, 2);
                    $table->float($column);
                });
            }
        };
        PHP);

    expect($file->lines(new NoFloatMoneyColumns))->toBe([12, 13, 14, 15, 16]);
});

it('reads only the migrations', function (string $path, Kind $kind, array $lines) {
    $file = TestFile::of($kind, $path, "<?php\n\n\$table->float('price');\n");

    expect($file->lines(new NoFloatMoneyColumns))->toBe($lines);
})->with([
    'a migration' => ['database/migrations/2026_01_01_000000_create_orders_table.php', Kind::MIGRATIONS, [3]],
    'a nested migration' => ['database/migrations/tenant/2026_01_01_000000_create_orders_table.php', Kind::MIGRATIONS, [3]],
    'the migration of a package in a monorepo' => ['packages/billing/database/migrations/2026_01_01_000000_create_orders_table.php', Kind::MIGRATIONS, [3]],
    'a model' => ['app/Models/Order.php', Kind::SOURCE, []],
    'source code that looks like a migration' => ['database/migrations/2026_01_01_000000_create_orders_table.php', Kind::SOURCE, []],
    'a factory' => ['database/factories/OrderFactory.php', Kind::SOURCE, []],
]);

it('fails a column the change renames to money on a line of its own', function () {
    $file = TestFile::of(Kind::MIGRATIONS, 'database/migrations/2026_01_01_000000_create_orders_table.php', "<?php\n\n\$table->float(\n    'price',\n);\n")
        ->changedFrom("<?php\n\n\$table->float(\n    'weight',\n);\n");

    expect($file->lines(new NoFloatMoneyColumns))->toBe([3]);
});
