<?php

declare(strict_types=1);

namespace PayumPayU\Request;

use Payum\Core\Request\Generic;

/**
 * Creates the order at PayU for the payment details and redirects the buyer
 * when PayU returns a redirect URI. Executed by the Capture action.
 */
final class CreateOrder extends Generic
{
}
