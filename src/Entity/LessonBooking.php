<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LessonBookingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LessonBookingRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_lesson_start', columns: ['starts_at'])]
class LessonBooking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $student;

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(length: 80)]
    private string $topic;

    #[ORM\Column(length: 20)]
    private string $status = 'proposed';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $student, \DateTimeImmutable $startsAt, string $topic)
    {
        $this->student = $student;
        $this->startsAt = $startsAt->setTimezone(new \DateTimeZone('UTC'));
        $this->topic = $topic;
        $this->createdAt = new \DateTimeImmutable();
        $student->addBooking($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): User
    {
        return $this->student;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isProposed(): bool
    {
        return 'proposed' === $this->status;
    }

    public function approve(): void
    {
        if (!$this->isProposed()) {
            throw new \LogicException('Only a proposed lesson can be approved.');
        }

        $this->status = 'approved';
    }

    public function decline(): void
    {
        if (!$this->isProposed()) {
            throw new \LogicException('Only a proposed lesson can be declined.');
        }

        $this->status = 'declined';
    }
}
