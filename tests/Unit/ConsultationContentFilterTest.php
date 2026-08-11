<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\ConsultationContentFilter;
use PHPUnit\Framework\TestCase;

final class ConsultationContentFilterTest extends TestCase
{
    public function testItBlocksAdvertisingPitches(): void
    {
        $filter = new ConsultationContentFilter();

        self::assertTrue($filter->isAdvertising('We can sell you a backlink package and a sponsored post.'));
    }

    public function testItAllowsARelevantTechnicalRequest(): void
    {
        $filter = new ConsultationContentFilter();

        self::assertFalse($filter->isAdvertising('Our application is slow after deployment and we need help finding the bottleneck.'));
    }
}
