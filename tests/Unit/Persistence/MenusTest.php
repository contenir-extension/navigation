<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Persistence;

use Contenir\Extension\Api\Editor\CurrentEditorInterface;
use Contenir\Extension\Api\Editor\Editor;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Navigation\Event\MenuPostChange;
use Contenir\Extension\Navigation\Persistence\Menus;
use Contenir\Extension\Navigation\Tests\TestAsset\Event\RecordingEvents;
use Contenir\Navigation\Menu\Navigation;
use Contenir\Navigation\Menu\PublishedBy;
use Contenir\Navigation\Publishing\Publisher;
use Contenir\Navigation\Storage\NavigationRepositoryInterface;
use Contenir\Navigation\Storage\TransactionInterface;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class MenusTest extends TestCase
{
    #[Test]
    public function aChangeIsAnnouncedToNoOneWithoutTheAddOnsEvents(): void
    {
        $this->menus(null)->changed(new Navigation('header', 'Header', id: 1, revisionId: 3));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function aChangeToAMenuNotYetPublishedIsNotAnnounced(): void
    {
        $events = new RecordingEvents();

        $this->menus(null, $events)->changed(new Navigation('header', 'Header', id: 1));

        static::assertSame([], $events->dispatched);
    }

    #[Test]
    public function aLiveMenusChangeIsAnnouncedByItsIdentifier(): void
    {
        $events = new RecordingEvents();

        $this->menus(null, $events)->changed(new Navigation('header', 'Header', id: 1, revisionId: 3));

        static::assertEquals([new MenuPostChange('header')], $events->dispatched);
    }

    #[Test]
    public function thePublisherIsTheEditorSignedIn(): void
    {
        $editors = $this->createStub(CurrentEditorInterface::class);
        $editors->method('current')->willReturn(new Editor('u-4', 'Ann Editor'));

        static::assertEquals(new PublishedBy('u-4', 'Ann Editor'), $this->menus($editors)->publisher());
    }

    #[Test]
    public function thereIsNoPublisherWhenNoOneIsSignedInOrTheHostCannotSay(): void
    {
        $editors = $this->createStub(CurrentEditorInterface::class);
        $editors->method('current')->willReturn(null);

        static::assertSame([null, null], [$this->menus($editors)->publisher(), $this->menus(null)->publisher()]);
    }

    private function menus(?CurrentEditorInterface $editors, ?RecordingEvents $events = null): Menus
    {
        $repository  = $this->createStub(NavigationRepositoryInterface::class);
        $transaction = $this->createStub(TransactionInterface::class);

        return new Menus(
            $repository,
            new Publisher($repository, $transaction, new FrozenClock(new DateTimeImmutable('2026-10-23 09:00'))),
            $transaction,
            editors: $editors,
            events: $events,
        );
    }
}
