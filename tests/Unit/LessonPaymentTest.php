<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\LessonPayment;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class LessonPaymentTest extends TestCase
{
    public function testApprovedPaymentIsIdempotent(): void
    {
        $payment = new LessonPayment(new User(), '1AB23456CD789012E');

        self::assertTrue($payment->approve());
        self::assertFalse($payment->approve());
        self::assertSame('APPROVED', $payment->getStatus());
        self::assertNotNull($payment->getReviewedAt());
    }

    public function testPaymentCanBeDeclinedOnlyOnce(): void
    {
        $payment = new LessonPayment(new User(), '1AB23456CD789012E');

        self::assertTrue($payment->decline());
        self::assertFalse($payment->decline());
        self::assertSame('DECLINED', $payment->getStatus());
    }
}
