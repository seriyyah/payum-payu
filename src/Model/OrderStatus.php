<?php

declare(strict_types=1);

namespace PayumPayU\Model;

/**
 * Order statuses of the PayU REST API 2.1.
 */
enum OrderStatus: string
{
    case New = 'NEW';
    case Pending = 'PENDING';
    case WaitingForConfirmation = 'WAITING_FOR_CONFIRMATION';
    case Completed = 'COMPLETED';
    case Canceled = 'CANCELED';

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Canceled;
    }

    /**
     * PayU delivers notifications in no guaranteed order. A status may only
     * move forward: a PENDING arriving after COMPLETED is stale and ignored.
     */
    public function supersedes(?self $current): bool
    {
        return $current === null || $this->rank() > $current->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::New => 0,
            self::Pending => 1,
            self::WaitingForConfirmation => 2,
            self::Completed, self::Canceled => 3,
        };
    }
}
