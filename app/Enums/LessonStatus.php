<?php

namespace App\Enums;

enum LessonStatus: string
{
    case Scheduled = 'scheduled';
    case Free = 'free';
    case Booked = 'booked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function canChangeTo(self $status): bool
    {
        return match ($this) {
            self::Scheduled => $status === self::Cancelled,
            self::Free => in_array($status, [self::Booked, self::Cancelled], true),
            self::Booked => in_array($status, [self::Free, self::Completed, self::Cancelled], true),
            default => false,
        };
    }
}
