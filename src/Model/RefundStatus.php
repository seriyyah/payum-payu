<?php

declare(strict_types=1);

namespace PayumPayU\Model;

enum RefundStatus: string
{
    case Pending = 'PENDING';
    case Canceled = 'CANCELED';
    case Finalized = 'FINALIZED';
}
