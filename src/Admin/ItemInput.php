<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Exception\LinkRejectedException;
use Contenir\Extension\Api\Form\Link;
use Contenir\Navigation\Exception\InvalidItemException;
use Contenir\Navigation\Exception\InvalidLinkException;
use Contenir\Navigation\Link\RecordTarget;
use Contenir\Navigation\Link\Url;
use Contenir\Navigation\Menu\Display;
use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\ItemKind;

/**
 * The item what was posted describes, read from an item's form.
 */
final readonly class ItemInput
{
    public function __construct(
        private Input $input,
    ) {}

    /**
     * What the item links to and its label, kept from $item where it is
     * drawn and placed. A page the link field asked for is linked to as
     * $created, once the handler has made it.
     *
     * @throws InvalidItemException  When the item breaks one of its rules.
     * @throws InvalidLinkException  When the address can't be held.
     * @throws LinkRejectedException When the link field's choice is incomplete.
     */
    public function item(?Item $item = null, ?RecordTarget $created = null): Item
    {
        $made = $this->made($this->link(), $created);

        return null === $item
            ? $made
            : new Item(
                $made->kind,
                $made->record,
                $made->url,
                $made->fragment,
                $made->label,
                $made->html,
                $item->display,
                $item->icon,
                ItemKind::Text === $made->kind ? null : $item->target,
                $item->class,
                $item->active,
                $item->id,
                $item->navigationId,
                $item->parentId,
                $item->sequence,
                $item->created,
            );
    }

    /**
     * What the link field chose: a record, an address, a page to create, or
     * null for text only.
     *
     * @throws LinkRejectedException When the choice is incomplete.
     */
    public function link(): ?Link
    {
        return Link::fromPosted($this->input->raw('link'));
    }

    /**
     * The parent chosen, or null for the top of the menu.
     */
    public function parent(): ?int
    {
        return $this->input->id('parent');
    }

    /**
     * $item drawn as the edit form says.
     *
     * @throws InvalidItemException When the drawing breaks one of its rules.
     */
    public function presented(Item $item): Item
    {
        return $item->withPresentation(
            $item->label,
            Display::tryFrom($this->input->posted('display')) ?? Display::Label,
            $this->input->postedOrNull('icon'),
            ItemKind::Text !== $item->kind && $this->input->checked('new_tab') ? Item::NEW_TAB : null,
            $this->input->postedOrNull('class'),
            $this->input->checked('active'),
        );
    }

    /**
     * @throws InvalidItemException When the item breaks one of its rules.
     * @throws InvalidLinkException When the address can't be held.
     */
    private function made(?Link $link, ?RecordTarget $created): Item
    {
        $label    = $this->input->postedOrNull('label');
        $fragment = $this->input->postedOrNull('fragment');
        $record   = $link?->record;
        if (null !== $record) {
            return Item::record(new RecordTarget($record->section, $record->id), $fragment, $label);
        }

        if (null !== $created) {
            return Item::record($created, $fragment, $label);
        }

        $address = $link?->address;

        return null === $address
            ? Item::text((string) $label, $this->input->postedOrNull('html'))
            : Item::url(Url::parse($address), (string) $label);
    }
}
