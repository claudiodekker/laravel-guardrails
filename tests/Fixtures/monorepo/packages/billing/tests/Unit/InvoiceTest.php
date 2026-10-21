<?php

namespace Acme\Billing\Tests\Unit;

use Acme\Billing\Invoice;
use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase
{
    public function test_it_keeps_the_amount(): void
    {
        $this->assertSame(1250, (new Invoice(1250))->amount);
    }
}
