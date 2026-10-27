<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\TestAsset\Event;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Settings\SettingsInterface;
use Contenir\Extension\Api\Url\UrlResolverInterface;
use Override;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * The host's context for the add-on, whose events go through RecordingEvents on their way to the
 * host's dispatcher.
 */
final readonly class RecordingContext implements ExtensionContextInterface
{
    public function __construct(
        private ExtensionContextInterface $inner,
        private RecordingEvents $events,
    ) {}

    #[Override]
    public function clock(): ClockInterface
    {
        return $this->inner->clock();
    }

    #[Override]
    public function events(): EventDispatcherInterface
    {
        return $this->events;
    }

    #[Override]
    public function handle(): Handle
    {
        return $this->inner->handle();
    }

    #[Override]
    public function logger(): LoggerInterface
    {
        return $this->inner->logger();
    }

    #[Override]
    public function services(): ContainerInterface
    {
        return $this->inner->services();
    }

    #[Override]
    public function settings(?string $section = null): SettingsInterface
    {
        return $this->inner->settings($section);
    }

    #[Override]
    public function sitePath(): string
    {
        return $this->inner->sitePath();
    }

    #[Override]
    public function urls(): UrlResolverInterface
    {
        return $this->inner->urls();
    }
}
