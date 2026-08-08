<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LessonBookingRepository;

final readonly class LessonSchedule
{
    private const array TIMES = ['09:00', '10:30', '13:00', '14:30', '16:00', '18:00'];

    public function __construct(private LessonBookingRepository $bookings)
    {
    }

    /** @return list<string> */
    public function availableTimes(\DateTimeImmutable $day): array
    {
        if ((int) $day->format('N') > 5 || !$this->isBookableDay($day)) {
            return [];
        }

        return array_values(array_diff(self::TIMES, $this->bookings->bookedTimesForDay($day)));
    }

    public function isValidSlot(\DateTimeImmutable $startsAt): bool
    {
        return in_array($startsAt->format('H:i'), $this->availableTimes($startsAt->setTime(0, 0)), true);
    }

    private function isBookableDay(\DateTimeImmutable $day): bool
    {
        $today = new \DateTimeImmutable('today', $day->getTimezone());

        return $day >= $today->modify('+1 day') && $day <= $today->modify('+28 days');
    }
}
