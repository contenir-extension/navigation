<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Admin;

use Contenir\Extension\Api\Event\ActionResult;
use Contenir\Extension\Api\Event\Outcome;
use Contenir\Extension\Navigation\Admin\PageRefusedException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PageRefusedExceptionTest extends TestCase
{
    #[Test]
    public function leavesOutAnEmptyMessage(): void
    {
        static::assertSame(
            'No room',
            PageRefusedException::from(new ActionResult(Outcome::Failed, null, errors: ['path' => [
                'No room',
            ]]))->getMessage(),
        );
    }

    #[Test]
    public function saysWhatTheSiteSaidAndEachFieldsErrors(): void
    {
        $result = new ActionResult(Outcome::Stopped, null, 'Some fields need attention', errors: [
            'path'  => ['That path is already in use'],
            'title' => ['Too long', 'Not unique'],
        ]);

        static::assertSame(
            'Some fields need attention That path is already in use Too long Not unique',
            PageRefusedException::from($result)->getMessage(),
        );
    }

    #[Test]
    public function saysWhyInItsOwnWords(): void
    {
        static::assertSame('Not here.', PageRefusedException::because('Not here.')->getMessage());
    }
}
