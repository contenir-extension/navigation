<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Admin;

use Contenir\Extension\Navigation\Admin\ItemChoices;
use Contenir\Navigation\Link\RecordTarget;
use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\ItemKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ItemChoicesTest extends TestCase
{
    private static function text(int $id, string $label, ?int $parent, int $sequence): Item
    {
        return new Item(
            ItemKind::Text,
            label: $label,
            id: $id,
            navigationId: 1,
            parentId: $parent,
            sequence: $sequence,
        );
    }

    #[Test]
    public function offersTheItemsIndentedByDepthLeavingOutOneAndWhatIsUnderIt(): void
    {
        $items = [
            self::text(1, 'Shop', null, 0),
            self::text(2, 'Sale', 1, 0),
            self::text(3, 'Clearance', 2, 0),
            self::text(4, 'News', null, 1),
            self::text(5, 'Archive', 4, 0),
            new Item(ItemKind::Record, record: new RecordTarget('page', 9), id: 6, navigationId: 1, sequence: 2),
        ];

        static::assertSame(
            [
                [
                    '' => 'The top of the menu',
                    1  => '— Shop',
                    2  => '— — Sale',
                    3  => '— — — Clearance',
                    4  => '— News',
                    5  => '— — Archive',
                    6  => '— page 9',
                ],
                ['' => 'The top of the menu', 1 => '— Shop', 2 => '— — Sale', 3 => '— — — Clearance', 6 => '— page 9'],
            ],
            [ItemChoices::parents($items), ItemChoices::parents($items, 4)],
        );
    }
}
