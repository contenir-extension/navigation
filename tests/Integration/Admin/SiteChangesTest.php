<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Integration\Admin;

use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Navigation\Admin\ItemAddHandler;
use Contenir\Extension\Navigation\Admin\ItemDeleteHandler;
use Contenir\Extension\Navigation\Admin\ItemEditHandler;
use Contenir\Extension\Navigation\Admin\ItemMoveHandler;
use Contenir\Extension\Navigation\Admin\ItemVisibilityHandler;
use Contenir\Extension\Navigation\Admin\MenuDeleteHandler;
use Contenir\Extension\Navigation\Admin\MenuEditHandler;
use Contenir\Extension\Navigation\Admin\PublishHandler;
use Contenir\Extension\Navigation\Admin\RestoreHandler;
use Contenir\Extension\Navigation\Tests\Trait\NavigationHostTrait;
use Contenir\Navigation\Menu\Item;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Each change that reaches the site is announced with MenuPostChange, so the CMS clears the
 * site's caches; a change to a menu not yet published stays in its draft and is not.
 */
#[Group('integration')]
#[Group('handler')]
final class SiteChangesTest extends TestCase
{
    use NavigationHostTrait;

    private int $menu;

    /**
     * @return array<string, array{class-string<HandlerInterface>, array<string, int>, array<string, string>}>
     */
    public static function changeProvider(): array
    {
        return [
            'adding an item'    => [
                ItemAddHandler::class,
                ['menu' => 1],
                ['link' => '{"kind":"text"}', 'label' => 'Sale', 'active' => '1'],
            ],
            'editing an item'   => [
                ItemEditHandler::class,
                ['item' => 1],
                ['link' => '{"kind":"text"}', 'label' => 'Shop now', 'active' => '1'],
            ],
            'deleting an item'  => [ItemDeleteHandler::class, ['item' => 1], []],
            'moving an item'    => [ItemMoveHandler::class, ['item' => 2], ['direction' => 'up']],
            'hiding an item'    => [ItemVisibilityHandler::class, ['item' => 1], []],
            'restoring'         => [RestoreHandler::class, ['revision' => 1], []],
            'editing the menu'  => [
                MenuEditHandler::class,
                ['menu' => 1],
                ['title' => 'Header', 'handle' => 'header', 'description' => ''],
            ],
            'deleting the menu' => [MenuDeleteHandler::class, ['menu' => 1], []],
        ];
    }

    /**
     * @param class-string<HandlerInterface> $handler
     * @param array<string, int>             $query
     * @param array<string, string>          $posted
     */
    #[Test]
    #[DataProvider('changeProvider')]
    public function aChangeToALiveMenuIsAnnounced(string $handler, array $query, array $posted): void
    {
        $this->answer(PublishHandler::class, ['menu' => $this->menu], []);

        $this->answer($handler, $query, $posted);

        static::assertSame(['header', 'header'], $this->announcedMenus());
    }

    /**
     * @param class-string<HandlerInterface> $handler
     * @param array<string, int>             $query
     * @param array<string, string>          $posted
     */
    #[Test]
    #[DataProvider('changeProvider')]
    public function aChangeToAMenuNotYetPublishedIsNotAnnounced(string $handler, array $query, array $posted): void
    {
        $this->answer($handler, $query, $posted);

        static::assertSame([], $this->announcedMenus());
    }

    #[Test]
    public function aLiveMenuGivenAnotherIdentifierIsAnnouncedByItsNewOne(): void
    {
        $this->answer(PublishHandler::class, ['menu' => $this->menu], []);

        $this->answer(
            MenuEditHandler::class,
            ['menu' => $this->menu],
            ['title' => 'Main', 'handle' => 'main', 'description' => ''],
        );

        static::assertSame(['header', 'main'], $this->announcedMenus());
    }

    #[Test]
    public function deletingAMenuThatIsGoneSaysSoAndAnnouncesNothing(): void
    {
        $result = $this->answer(MenuDeleteHandler::class, ['menu' => 9], []);

        static::assertSame(
            [[['level' => 'warning', 'text' => 'That menu no longer exists.']], []],
            [$result->messages, $this->announcedMenus()],
        );
    }

    #[Test]
    public function publishingAnnouncesTheMenu(): void
    {
        $this->answer(PublishHandler::class, ['menu' => $this->menu], []);

        static::assertSame(['header'], $this->announcedMenus());
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
        $this->menu = $this->menu();
        $this->item($this->menu, Item::text('Shop'));
        $this->item($this->menu, Item::text('Sale'), position: 1);
    }
}
