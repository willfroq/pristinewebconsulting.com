<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\LessonBooking;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testEmailIsNormalizedAndRoleIsGuaranteed(): void
    {
        $user = (new User())
            ->setName('  Ada Lovelace  ')
            ->setEmail('ADA@Example.COM ')
            ->setRoles(['ROLE_STUDENT']);

        self::assertSame('Ada Lovelace', $user->getName());
        self::assertSame('ada@example.com', $user->getUserIdentifier());
        self::assertSame(['ROLE_STUDENT', 'ROLE_USER'], $user->getRoles());
    }

    public function testPaidLessonCreditsCanBeGrantedAndConsumed(): void
    {
        $user = new User();
        self::assertFalse($user->hasLessonCredit());

        $user->grantLessonCredit();
        self::assertTrue($user->hasLessonCredit());
        self::assertSame(1, $user->getLessonCredits());

        $user->consumeLessonCredit();
        self::assertFalse($user->hasLessonCredit());
        self::assertSame(0, $user->getLessonCredits());
    }

    public function testAccountCanBeMarkedAsEmailVerified(): void
    {
        $user = new User();
        self::assertFalse($user->isVerified());

        $user->markVerified();

        self::assertTrue($user->isVerified());
    }

    public function testLessonProposalCanBeApproved(): void
    {
        $proposal = new LessonBooking(
            new User(),
            new \DateTimeImmutable('+1 day'),
            'Symfony & PHP',
        );
        self::assertTrue($proposal->isProposed());

        $proposal->approve();

        self::assertSame('approved', $proposal->getStatus());
    }
}
