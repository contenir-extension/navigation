<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Integration\Admin;

use Contenir\Extension\Api\Editor\CurrentEditorInterface;
use Contenir\Extension\Api\Editor\Editor;
use Contenir\Extension\Navigation\Admin\ItemVisibilityHandler;
use Contenir\Extension\Navigation\Admin\PublishHandler;
use Contenir\Extension\Navigation\Tests\TestAsset\Editor\ChangeableEditor;
use Contenir\Extension\Navigation\Tests\Trait\NavigationHostTrait;
use Contenir\Navigation\Menu\Item;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Changes that put a revision on the site, recorded with who made it: publishing a menu, and the
 * visibility switch on a live one, each saying why when the editor can't be recorded.
 */
#[Group('integration')]
#[Group('handler')]
final class RecordedChangesTest extends TestCase
{
    use NavigationHostTrait;

    #[Test]
    public function publishingSaysWhyWhenItsEditorCannotBeRecorded(): void
    {
        $this->setUpHost([
            CurrentEditorInterface::class => new ChangeableEditor(new Editor(str_repeat('a', times: 65), 'Ann Editor')),
        ]);
        $menu = $this->menu();

        $result = $this->answer(PublishHandler::class, ['menu' => $menu], []);

        static::assertSame(
            ["/admin/extension/navigation/menus?menu={$menu}", 'danger'],
            [$result->url, $result->messages[0]['level'] ?? null],
        );
    }

    #[Test]
    public function theSwitchOnALiveMenuSaysWhyWhenItsEditorCannotBeRecorded(): void
    {
        $editors = new ChangeableEditor(new Editor('ann', 'Ann Editor'));
        $this->setUpHost([CurrentEditorInterface::class => $editors]);
        $menu = $this->menu();
        $shop = $this->item($menu, Item::text('Shop'));
        $this->answer(PublishHandler::class, ['menu' => $menu], []);
        $editors->editor = new Editor(str_repeat('a', times: 65), 'Ann Editor');

        $result = $this->answer(ItemVisibilityHandler::class, ['item' => (int) $shop->id], ['active' => '1']);

        static::assertSame(
            ["/admin/extension/navigation/menus?menu={$menu}", 'danger'],
            [$result->url, $result->messages[0]['level'] ?? null],
        );
    }
}
