<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\LessonBooking;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class LessonBookingTest extends TestCase
{
    public function testStartTimeIsNormalizedToUtcWithoutChangingTheInstant(): void
    {
        $amsterdamStart = new \DateTimeImmutable(
            '2026-07-15 09:00:00',
            new \DateTimeZone('Europe/Amsterdam'),
        );

        $booking = new LessonBooking(new User(), $amsterdamStart, 'Symfony & PHP');

        self::assertSame('UTC', $booking->getStartsAt()->getTimezone()->getName());
        self::assertSame('2026-07-15 07:00:00', $booking->getStartsAt()->format('Y-m-d H:i:s'));
        self::assertSame($amsterdamStart->getTimestamp(), $booking->getStartsAt()->getTimestamp());
    }
}
