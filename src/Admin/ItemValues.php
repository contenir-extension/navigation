<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\ItemKind;

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
            'links_to' => match ($item->kind) {
                ItemKind::Record => ItemForm::LINKS_TO_RECORD,
                ItemKind::Url    => ItemForm::LINKS_TO_URL,
                ItemKind::Text   => ItemForm::LINKS_TO_NOTHING,
            },
            'record'   => null === $item->record ? null : "{$item->record->section}:{$item->record->id}",
            'url'      => $item->url?->display(),
            'label'    => $item->label,
            'fragment' => $item->fragment,
            'html'     => $item->html,
            'parent'   => null === $item->parentId ? ItemChoices::TOP : (string) $item->parentId,
            'display'  => $item->display->value,
            'icon'     => $item->icon,
            'new_tab'  => Item::NEW_TAB === $item->target,
            'class'    => $item->class,
            'active'   => $item->active,
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    public static function posted(Input $input): array
    {
        $values = [];
        foreach ([
            'links_to',
            'record',
            'url',
            'label',
            'fragment',
            'html',
            'parent',
            'display',
            'icon',
            'class',
        ] as $name) {
            $values[$name] = $input->posted($name);
        }

        return [...$values, 'new_tab' => $input->checked('new_tab'), 'active' => $input->checked('active')];
    }
}
