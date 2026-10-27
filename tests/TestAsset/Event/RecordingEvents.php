<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\TestAsset\Event;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The add-on's event dispatcher, keeping each event it is given before passing it on, so a test
 * can tell what the add-on fired.
 */
final class RecordingEvents implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    public function __construct(
        private readonly ?EventDispatcherInterface $inner = null,
    ) {}

    #[Override]
    public function dispatch(object $event): object
    {
        $this->dispatched[] = $event;

        return null === $this->inner ? $event : $this->inner->dispatch($event);
    }
}
