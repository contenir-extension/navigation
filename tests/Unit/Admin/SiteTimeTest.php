<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Admin;

use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Navigation\Admin\SiteTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SiteTimeTest extends TestCase
{
    #[Test]
    public function showsATimeTheTablesHoldInUtcInTheSitesTimezone(): void
    {
        $time = new SiteTime(new FrozenClock(new DateTimeImmutable(
            '2026-10-26 09:00',
            new DateTimeZone('Australia/Melbourne'),
        )));

        static::assertSame(
            '26 Oct 2026, 11:22am',
            $time->show(new DateTimeImmutable('2026-10-26 00:22', new DateTimeZone('UTC'))),
        );
    }
}
