<?php

declare(strict_types=1);

namespace PayumPayU\Model;

/**
 * Value of the "recurring" order parameter.
 *
 * FIRST registers the card for later merchant-initiated charges and needs a
 * card token obtained from the PayU secure form. STANDARD charges a card
 * token that was registered earlier.
 */
enum Recurring: string
{
    case First = 'FIRST';
    case Standard = 'STANDARD';
}
