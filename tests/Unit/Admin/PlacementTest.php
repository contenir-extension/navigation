<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Admin;

use Contenir\Extension\Navigation\Admin\Placement;
use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\ItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class PlacementTest extends TestCase
{
    #[Test]
    public function aNewItemGoesAfterThoseUnderItsParent(): void
    {
        $items = [
            Item::text('Shop')->withPlacement(1, null, 0),
            Item::text('Sale')->withPlacement(1, 1, 0),
            Item::text('Gifts')->withPlacement(1, 1, 1),
            Item::text('News')->withPlacement(1, null, 1),
        ];

        static::assertSame([2, 2, 0], [
            Placement::last($items, null),
            Placement::last($items, 1),
            Placement::last($items, 9),
        ]);
    }

    #[Test]
    public function findsTheParentAmongTheItemsAndNoneForTheTopOrOneThatIsGone(): void
    {
        $items = [new Item(ItemKind::Text, label: 'Shop', id: 1), new Item(ItemKind::Text, label: 'Sale', id: 2)];

        static::assertSame(['Sale', null, null], [
            Placement::item($items, 2)?->label,
            Placement::item($items, null),
            Placement::item($items, 9),
        ]);
    }
}
