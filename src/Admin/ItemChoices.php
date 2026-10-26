<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Navigation\Menu\Family;
use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\Node;
use Contenir\Navigation\Menu\Tree;

use function in_array;
use function sprintf;
use function str_repeat;

/**
 * The items an item's form offers to put it under.
 */
final readonly class ItemChoices
{
    /**
     * The choice for an item at the top of its menu.
     */
    public const string TOP = '';

    /**
     * The items of a menu an item can sit under, indented by depth, leaving
     * out $moving and everything nested under it; the top of the menu first.
     *
     * @param list<Item> $items
     *
     * @return array<string, string>
     */
    public static function parents(array $items, ?int $moving = null): array
    {
        $excluded = null === $moving ? [] : [$moving, ...Family::descendants($items, $moving)];

        return [self::TOP => 'None (top level)'] + self::indented(Tree::build($items), $excluded, 0);
    }

    /**
     * @param list<Node>       $nodes
     * @param list<int>        $excluded
     * @param non-negative-int $depth
     *
     * @return array<string, string>
     */
    private static function indented(array $nodes, array $excluded, int $depth): array
    {
        $choices = [];
        foreach ($nodes as $node) {
            $id = $node->item->id;
            if (in_array($id, $excluded, strict: true)) {
                continue;
            }

            $choices[(string) $id] =
                str_repeat('— ', times: $depth + 1) . ($node->item->label ?? self::unlabelled($node->item));
            foreach (self::indented($node->children, $excluded, $depth + 1) as $child => $label) {
                $choices[$child] = $label;
            }
        }

        return $choices;
    }

    private static function unlabelled(Item $item): string
    {
        return sprintf('%s %s', (string) $item->record?->section, (string) $item->record?->id);
    }
}
