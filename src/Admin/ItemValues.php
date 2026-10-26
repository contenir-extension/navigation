<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Exception\LinkRejectedException;
use Contenir\Extension\Api\Form\Link;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Navigation\Menu\Item;

/**
 * The values an item's form shows: an item's own, or what was posted when
 * the form is shown again.
 */
final class ItemValues
{
    /**
     * @return array<string, scalar|null>
     */
    public static function of(Item $item): array
    {
        return [
            'link'    => self::link($item)?->encode(),
            'label'   => $item->label,
            'html'    => $item->html,
            'parent'  => null === $item->parentId ? ItemChoices::TOP : (string) $item->parentId,
            'display' => $item->display->value,
            'icon'    => $item->icon,
            'new_tab' => Item::NEW_TAB === $item->target,
            'class'   => $item->class,
            'active'  => $item->active,
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    public static function posted(Input $input): array
    {
        $values = [];
        foreach ([
            'label',
            'html',
            'parent',
            'display',
            'icon',
            'class',
        ] as $name) {
            $values[$name] = $input->posted($name);
        }

        try {
            $link = Link::fromPosted($input->raw('link'))?->encode();
        } catch (LinkRejectedException) {
            $link = null;
        }

        return [
            ...$values,
            'link'    => $link,
            'new_tab' => $input->checked('new_tab'),
            'active'  => $input->checked('active'),
        ];
    }

    /**
     * What an item links to as its link field holds it, or null for text.
     */
    private static function link(Item $item): ?Link
    {
        if (null !== $item->record) {
            return Link::record(new RecordReference($item->record->section, $item->record->id), $item->fragment);
        }

        return null === $item->url ? null : Link::address($item->url->display());
    }
}
