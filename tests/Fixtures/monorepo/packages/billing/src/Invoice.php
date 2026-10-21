<?php

namespace Acme\Billing;

class Invoice
{
    /**
     * Create an invoice for the given amount in cents.
     */
    public function __construct(public readonly int $amount)
    {
        //
    }
}
