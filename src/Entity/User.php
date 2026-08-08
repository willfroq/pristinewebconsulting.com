<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity(fields: ['email'], message: 'An account already exists for this email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(options: ['default' => 0])]
    private int $lessonCredits = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    /** @var Collection<int, LessonBooking> */
    #[ORM\OneToMany(mappedBy: 'student', targetEntity: LessonBooking::class, orphanRemoval: true)]
    private Collection $bookings;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new \LogicException('A user must have an email address.');
        }

        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getLessonCredits(): int
    {
        return $this->lessonCredits;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function markVerified(): void
    {
        $this->isVerified = true;
    }

    public function hasLessonCredit(): bool
    {
        return $this->lessonCredits > 0;
    }

    public function grantLessonCredit(): void
    {
        ++$this->lessonCredits;
    }

    public function consumeLessonCredit(): void
    {
        if (!$this->hasLessonCredit()) {
            throw new \LogicException('No paid lesson credit is available.');
        }

        --$this->lessonCredits;
    }

    /** @return Collection<int, LessonBooking> */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(LessonBooking $booking): void
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
        }
    }

    public function hasLearningActivity(): bool
    {
        return $this->hasLessonCredit() || !$this->bookings->isEmpty();
    }

    public function hasPendingLessonProposal(): bool
    {
        return $this->bookings->exists(
            static fn (int $key, LessonBooking $booking): bool => $booking->isProposed(),
        );
    }
}
