<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\ConsultationSubmissionLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ConsultationSubmissionLimiterTest extends TestCase
{
    public function testItLimitsRepeatedSubmissionsFromTheSameIpAddress(): void
    {
        $limiter = new ConsultationSubmissionLimiter(new ArrayAdapter());

        self::assertTrue($limiter->allows('203.0.113.20', 'first@example.com'));
        self::assertTrue($limiter->allows('203.0.113.20', 'second@example.com'));
        self::assertTrue($limiter->allows('203.0.113.20', 'third@example.com'));
        self::assertFalse($limiter->allows('203.0.113.20', 'fourth@example.com'));
    }

    public function testItLimitsRepeatedSubmissionsToTheSameEmailAddress(): void
    {
        $limiter = new ConsultationSubmissionLimiter(new ArrayAdapter());

        self::assertTrue($limiter->allows('203.0.113.20', 'person@example.com'));
        self::assertTrue($limiter->allows('203.0.113.21', 'person@example.com'));
        self::assertFalse($limiter->allows('203.0.113.22', 'person@example.com'));
    }
}
