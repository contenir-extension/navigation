<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * A time as the Navigation screens show it, such as "26 Oct 2026, 11:22am": in the site's
 * timezone, which is the timezone of the clock the host lends, though the menus' tables hold UTC.
 */
final readonly class SiteTime
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function show(DateTimeImmutable $time): string
    {
        return $time->setTimezone($this->clock->now()->getTimezone())->format('j M Y, g:ia');
    }
}
