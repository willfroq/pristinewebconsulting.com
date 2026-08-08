<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Service\LessonSchedule;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('SlotPicker')]
final class SlotPicker
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $selectedDate = '';

    public function __construct(private readonly LessonSchedule $schedule)
    {
    }

    public function mount(): void
    {
        if ('' === $this->selectedDate) {
            $this->selectedDate = $this->firstWeekday()->format('Y-m-d');
        }
    }

    /** @return list<array{value: string, label: string}> */
    public function getDates(): array
    {
        $dates = [];
        $day = new \DateTimeImmutable('tomorrow', new \DateTimeZone('Europe/Amsterdam'));
        while (count($dates) < 10) {
            if ((int) $day->format('N') <= 5) {
                $dates[] = ['value' => $day->format('Y-m-d'), 'label' => $day->format('D, M j')];
            }
            $day = $day->modify('+1 day');
        }

        return $dates;
    }

    /** @return list<string> */
    public function getAvailableTimes(): array
    {
        $day = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $this->selectedDate,
            new \DateTimeZone('Europe/Amsterdam'),
        );

        return false === $day ? [] : $this->schedule->availableTimes($day);
    }

    private function firstWeekday(): \DateTimeImmutable
    {
        $day = new \DateTimeImmutable('tomorrow', new \DateTimeZone('Europe/Amsterdam'));
        while ((int) $day->format('N') > 5) {
            $day = $day->modify('+1 day');
        }

        return $day;
    }
}
