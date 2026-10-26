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
 * The visibility switch on a live menu, whose change goes on the site as a revision recorded
 * with who made it.
 */
#[Group('integration')]
#[Group('handler')]
final class ItemVisibilityHandlerTest extends TestCase
{
    use NavigationHostTrait;

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
