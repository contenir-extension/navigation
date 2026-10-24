<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Navigation\Menu\Item;

/**
 * Where an item goes when it is added under a parent: after the items
 * already there.
 */
final class Placement
{
    /**
     * The position after the last item under $parentId, or under the top of
     * the menu when it is null.
     *
     * @param list<Item> $items
     */
    public static function last(array $items, ?int $parentId): int
    {
        $count = 0;
        foreach ($items as $item) {
            if ($parentId !== $item->parentId) {
                continue;
            }

            $count++;
        }

        return $count;
    }
}
