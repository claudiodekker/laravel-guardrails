<?php

namespace Acme\Crm\Tests\Feature;

use Acme\Crm\Contact;
use PHPUnit\Framework\TestCase;

class ContactTest extends TestCase
{
    public function test_it_keeps_the_name(): void
    {
        $this->assertSame('Ada', (new Contact('Ada'))->name);
    }
}
