<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Navigation\Exception\InvalidItemException;
use Contenir\Navigation\Exception\InvalidLinkException;
use Contenir\Navigation\Link\RecordTarget;
use Contenir\Navigation\Link\Url;
use Contenir\Navigation\Menu\Display;
use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\ItemKind;

use function strpos;
use function substr;

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
     * drawn and placed.
     *
     * @throws InvalidItemException When the item breaks one of its rules.
     * @throws InvalidLinkException When the address can't be held.
     */
    public function item(?Item $item = null): Item
    {
        $label = $this->input->postedOrNull('label');
        $made  = match ($this->input->posted('links_to')) {
            ItemForm::LINKS_TO_RECORD  => Item::record($this->record(), $this->input->postedOrNull('fragment'), $label),
            ItemForm::LINKS_TO_NOTHING => Item::text((string) $label, $this->input->postedOrNull('html')),
            default                    => Item::url(Url::parse($this->input->posted('url')), (string) $label),
        };

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
     * @throws InvalidItemException When no record was chosen.
     */
    private function record(): RecordTarget
    {
        $choice = $this->input->posted('record');
        $colon  = strpos($choice, needle: ':');
        if (false === $colon) {
            throw InvalidItemException::because('Choose the page or record the item links to');
        }

        return new RecordTarget(substr($choice, offset: 0, length: $colon), substr($choice, $colon + 1));
    }
}
