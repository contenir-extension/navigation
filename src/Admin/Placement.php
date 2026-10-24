<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Navigation\Menu\Item;

/**
 * Where an item goes when it is added under a parent: after the items
 * already there; and the parent itself.
 */
final class Placement
{
    /**
     * The item with $id among $items, or null for the top of the menu or an
     * item that isn't there.
     *
     * @param list<Item> $items
     */
    public static function item(array $items, ?int $id): ?Item
    {
        foreach ($items as $item) {
            if (null !== $id && $id === $item->id) {
                return $item;
            }
        }

        return null;
    }

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
