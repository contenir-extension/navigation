<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Integration\Contract;

use Contenir\Extension\Api\Conformance\AbstractAddOnConformanceTestCase;
use Contenir\Extension\Api\ExtensionInterface;
use Contenir\Extension\Navigation\NavigationExtension;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Group;

use function dirname;

#[Group('integration')]
#[Group('conformance')]
final class NavigationAddOnTest extends AbstractAddOnConformanceTestCase
{
    #[Override]
    protected function extension(): ExtensionInterface
    {
        return new NavigationExtension();
    }

    #[Override]
    protected function handle(): string
    {
        return NavigationExtension::HANDLE;
    }

    #[Override]
    protected function http(): Psr17Factory
    {
        return new Psr17Factory();
    }

    #[Override]
    protected function root(): string
    {
        return dirname(__DIR__, levels: 3);
    }
}
