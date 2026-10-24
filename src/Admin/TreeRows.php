<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Record\RecordsInterface;
use Contenir\Extension\Api\Record\RecordStatus;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Navigation\Link\RecordTarget;
use Contenir\Navigation\Link\UrlKind;
use Contenir\Navigation\Menu\Display;
use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\Node;

use function sprintf;

/**
 * A menu's tree as the Navigation screen draws it: each item's label,
 * icon and path, a badge where the site won't show it as it is, how it is
 * drawn, and the items nested under it. A record item is described by the
 * host's records, so its title, path and status are the record's own.
 */
final readonly class TreeRows
{
    /**
     * Badges for records the site won't show, by status.
     *
     * @var array<string, string>
     */
    private const array NOT_LIVE = [
        RecordStatus::Draft->value       => 'Draft',
        RecordStatus::Unpublished->value => 'Unpublished',
        RecordStatus::Archived->value    => 'Archived',
    ];

    public function __construct(
        private ?RecordsInterface $records,
    ) {}

    /**
     * How the item is drawn on the site, in words.
     */
    private static function drawn(Item $item): string
    {
        return match ($item->display) {
            Display::Label     => 'Label',
            Display::Icon      => sprintf('Icon "%s"', (string) $item->icon),
            Display::IconLabel => sprintf('Icon "%s" with label', (string) $item->icon),
        };
    }

    /**
     * The icon for an address of $kind.
     */
    private static function icon(UrlKind $kind): string
    {
        return match ($kind) {
            UrlKind::Path     => 'link',
            UrlKind::External => 'external-link',
            UrlKind::Fragment => 'hash',
            UrlKind::Email    => 'mail',
            UrlKind::Phone    => 'phone',
        };
    }

    /**
     * @param list<Node> $nodes
     *
     * @return list<array<string, mixed>>
     */
    public function rows(array $nodes): array
    {
        $rows = [];
        foreach ($nodes as $node) {
            $row             = $this->row($node->item);
            $row['children'] = $this->rows($node->children);
            $rows[]          = $row;
        }

        return $rows;
    }

    /**
     * The label, icon, path and badge for what the item links to: its
     * address, its record, or nothing.
     *
     * @return array{string, string, string|null, string|null}
     */
    private function link(Item $item): array
    {
        $url = $item->url;
        if (null !== $url) {
            return [(string) $item->label, self::icon($url->kind), $url->display(), null];
        }

        $record = $item->record;

        return null === $record ? [(string) $item->label, 'typography', null, null] : $this->record($item, $record);
    }

    /**
     * A record item's label, icon, path and badge, from the record where the
     * host describes it; Missing when the host lends records and has no such
     * record.
     *
     * @return array{string, string, string|null, string|null}
     */
    private function record(Item $item, RecordTarget $target): array
    {
        $summary = $this->records?->describe(new RecordReference($target->section, $target->id));
        if (null === $summary) {
            $label = $item->label ?? sprintf('%s %s', $target->section, $target->id);

            return [$label, 'file', null, null === $this->records ? null : 'Missing'];
        }

        $path = null === $item->fragment ? $summary->path : "{$summary->path}#{$item->fragment}";

        return [$item->label ?? $summary->title, 'file', $path, self::NOT_LIVE[$summary->status->value] ?? null];
    }

    /**
     * @return array{id: int, label: string, icon: string, path: string|null, badge: string|null, drawn: string}
     */
    private function row(Item $item): array
    {
        [$label, $icon, $path, $badge] = $this->link($item);

        return [
            'id'    => (int) $item->id,
            'label' => $label,
            'icon'  => $icon,
            'path'  => $path,
            'badge' => $item->active ? $badge : 'Hidden',
            'drawn' => self::drawn($item),
        ];
    }
}
