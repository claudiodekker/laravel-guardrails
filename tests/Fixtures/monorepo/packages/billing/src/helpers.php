<?php

/**
 * Format the amount in cents as euros.
 */
function euros(int $cents): string
{
    return number_format($cents / 100, 2).' EUR';
}
