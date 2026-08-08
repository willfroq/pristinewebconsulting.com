<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LessonPaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LessonPaymentRepository::class)]
#[ORM\Index(name: 'idx_lesson_payment_student', columns: ['student_id'])]
class LessonPayment
{
    public const int PRICE_CENTS = 8900;
    public const string CURRENCY = 'EUR';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $student;

    #[ORM\Column(length: 40, unique: true)]
    private string $paypalReference;

    #[ORM\Column(length: 20)]
    private string $status = 'PENDING';

    #[ORM\Column]
    private int $amountCents = self::PRICE_CENTS;

    #[ORM\Column(length: 3)]
    private string $currency = self::CURRENCY;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    public function __construct(User $student, string $paypalReference)
    {
        $this->student = $student;
        $this->paypalReference = mb_strtoupper(trim($paypalReference));
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): User
    {
        return $this->student;
    }

    public function getPayPalReference(): string
    {
        return $this->paypalReference;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function isPending(): bool
    {
        return 'PENDING' === $this->status;
    }

    public function approve(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->status = 'APPROVED';
        $this->reviewedAt = new \DateTimeImmutable();

        return true;
    }

    public function decline(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->status = 'DECLINED';
        $this->reviewedAt = new \DateTimeImmutable();

        return true;
    }
}
