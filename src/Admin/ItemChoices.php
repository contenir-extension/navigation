<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Record\RecordsInterface;
use Contenir\Navigation\Menu\Family;
use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\Node;
use Contenir\Navigation\Menu\Tree;

use function in_array;
use function sprintf;
use function str_repeat;

/**
 * The choices an item's form offers: the records it can link to, and the
 * items it can sit under.
 */
final readonly class ItemChoices
{
    /**
     * The most records the form lists; the link picker searches the rest.
     */
    public const int RECORDS = 500;

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

        return [self::TOP => 'The top of the menu'] + self::indented(Tree::build($items), $excluded, 0);
    }

    /**
     * Every record in the site's linkable sections, by "<section>:<id>",
     * labelled with its section, title and path; none when the host lends no
     * records.
     *
     * @return array<string, string>
     */
    public static function records(?RecordsInterface $records): array
    {
        if (null === $records) {
            return [];
        }

        $titles = [];
        foreach ($records->sections() as $section) {
            $titles[$section->section] = $section->title;
        }

        $choices = [];
        foreach ($records->search('', limit: self::RECORDS) as $summary) {
            $label = sprintf(
                '%s: %s',
                $titles[$summary->record->section] ?? $summary->record->section,
                $summary->title,
            );
            $choices["{$summary->record->section}:{$summary->record->id}"] = null === $summary->path
                ? $label
                : "{$label} ({$summary->path})";
        }

        return $choices;
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
