<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\NoNamespaces;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;

it('flags a name from the namespace however it is used', function (string $code) {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Pay.php', "<?php\n\nnamespace App\\Services;\n\n{$code}\n");

    expect($file->lines(new NoNamespaces(['Acme\Billing'])))->toBe([5]);
})->with([
    'an import' => 'use Acme\Billing\Invoice;',
    'new' => 'new \Acme\Billing\Invoice;',
    'a type hint' => 'function pay(\Acme\Billing\Invoice $invoice) {}',
    'an extends' => 'class Pay extends \Acme\Billing\Gateway {}',
    'an attribute' => '#[\Acme\Billing\Billable] class Pay {}',
    'a fully qualified ::class' => '$class = \Acme\Billing\Invoice::class;',
    'a static call' => '\Acme\Billing\Charge::of(1);',
    'a function of the namespace' => '\Acme\Billing\charge(1);',
    'the namespace itself' => 'use Acme\Billing;',
]);

it('leaves a name outside the namespace alone', function (string $code) {
    $file = TestFile::of(Kind::SOURCE, 'app/Services/Pay.php', "<?php\n\nnamespace App\\Services;\n\n{$code}\n");

    expect($file->lines(new NoNamespaces(['Acme\Billing'])))->toBe([]);
})->with([
    'a sibling namespace' => 'use Acme\BillingExtra\Invoice;',
    'another namespace' => 'use Acme\Shop\Cart;',
    'a class of the same short name' => 'new \App\Billing\Invoice;',
]);

it('compares names ignoring case, with the backslashes around a namespace trimmed', function () {
    $check = new NoNamespaces(['\ACME\billing\\']);

    $file = TestFile::of(Kind::SOURCE, 'app/Services/Pay.php', "<?php\n\nuse acme\\Billing\\Invoice;\n");

    expect($file->lines($check))->toBe([3])
        ->and($check->description())->toBe('No name from ACME\billing.');
});

it('describes the namespaces it forbids, naming the first three and counting the rest', function (NoNamespaces $check, string $description) {
    expect($check->description())->toBe($description);
})->with([
    'one' => [new NoNamespaces(['Acme\Billing']), 'No name from Acme\Billing.'],
    'two' => [new NoNamespaces(['Acme\Billing', 'Acme\Shop']), 'No name from Acme\Billing or Acme\Shop.'],
    'four' => [new NoNamespaces(['Acme\Billing', 'Acme\Shop', 'Acme\Legacy', 'Acme\Vendor']), 'No name from Acme\Billing, Acme\Shop, Acme\Legacy and 1 more.'],
]);

it('names the namespaces in its message unless it is given one', function () {
    expect((new NoNamespaces(['Acme\Billing', 'Acme\Shop', 'Acme\Legacy', 'Acme\Vendor']))->message())->toBe('Remove the dependency on Acme\Billing, Acme\Shop, Acme\Legacy or Acme\Vendor, which this code may not use.')
        ->and((new NoNamespaces(['Acme\Billing'], message: 'Bill through the Payments module.'))->message())->toBe('Bill through the Payments module.');
});

it('names the namespaces it takes and its message option at the end of its explanation', function () {
    expect((new NoNamespaces(['Acme\Billing']))->explanation())->toEndWith("It takes the namespaces as in new NoNamespaces(['Acme\\Billing']), and message: replaces the default message.");
});

it('refuses to forbid no namespace', function () {
    expect(fn () => new NoNamespaces([]))
        ->toThrow(InvalidArgumentException::class, "NoNamespaces forbids nothing. List a namespace, as new NoNamespaces(['Acme\\Billing']).");
});
