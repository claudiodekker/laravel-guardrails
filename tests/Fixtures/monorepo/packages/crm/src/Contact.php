<?php

namespace Acme\Crm;

class Contact
{
    /**
     * Create a contact with the given name.
     */
    public function __construct(public readonly string $name)
    {
        //
    }
}
