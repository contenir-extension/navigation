<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Event\Outcome;
use Contenir\Extension\Api\Form\Link;
use Contenir\Extension\Api\Record\NewRecord;
use Contenir\Extension\Api\Record\RecordsInterface;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Navigation\Link\RecordTarget;
use Contenir\Navigation\Menu\Item;

/**
 * A page the item form's link field asks for, made through the site's
 * records under the item's parent when that links to a page of the same
 * section, and the path such pages start with.
 */
final class NewPage
{
    /**
     * The record made for the link field's new page, or null when it asked
     * for none. Run inside the menu's transaction, so the page rolls back
     * with the item.
     *
     * @throws PageRefusedException When the site lends no records or doesn't make the page.
     */
    public static function made(?RecordsInterface $records, ?Link $link, ?Item $under): ?RecordTarget
    {
        $new = $link?->newRecord;
        if (null === $new) {
            return null;
        }

        if (null === $records) {
            throw PageRefusedException::because('This site can\'t make pages from here.');
        }

        $parent = $under?->record;
        $result = $records->create(new NewRecord(
            $new->section,
            $new->title,
            $new->path,
            $new->workflow,
            $parent?->section === $new->section ? new RecordReference($new->section, $parent->id) : null,
        ));
        if (Outcome::Done !== $result->outcome || null === $result->id) {
            throw PageRefusedException::from($result);
        }

        return new RecordTarget($new->section, $result->id);
    }

    /**
     * The path of the page $under links to, which a new page under it
     * starts with, or null when it links to none the site knows.
     */
    public static function prefix(?RecordsInterface $records, ?Item $under): ?string
    {
        $record = $under?->record;

        return null === $record ? null : $records?->describe(new RecordReference($record->section, $record->id))?->path;
    }
}
