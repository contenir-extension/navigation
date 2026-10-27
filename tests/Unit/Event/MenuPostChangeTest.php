<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Event;

use Contenir\Extension\Api\Event\Outcome;
use Contenir\Extension\Navigation\Event\MenuPostChange;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class MenuPostChangeTest extends TestCase
{
    #[Test]
    public function aMenusChangeReachedTheSite(): void
    {
        $change = new MenuPostChange('header');

        static::assertSame(
            ['header', Outcome::Done, true, ''],
            [$change->handle, $change->outcome(), $change->succeeded(), $change->reason()],
        );
    }
}
