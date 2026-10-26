<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Navigation\Exception\NotFoundException;
use Contenir\Navigation\Exception\StorageException;
use Contenir\Navigation\Menu\Navigation;
use Contenir\Navigation\Publishing\Publisher;
use Contenir\Navigation\Storage\NavigationRepositoryInterface;

/**
 * Where a menu stands, as its header says: live or not, when it was last
 * published and by whom, and how many changes its draft holds.
 */
final class MenuState
{
    /**
     * @return array{live: bool, published: string|null, by: string|null, changes: int}
     *
     * @throws NotFoundException When the menu's live revision is gone.
     * @throws StorageException
     */
    public static function of(
        Navigation $menu,
        NavigationRepositoryInterface $repository,
        Publisher $publisher,
        SiteTime $time,
    ): array {
        $revision = null === $menu->revisionId ? null : $repository->revision($menu->revisionId);

        return [
            'live'      => null !== $revision,
            'published' => null === $revision ? null : $time->show($revision->created),
            'by'        => $revision?->publishedBy?->name,
            'changes'   => $publisher->unpublishedChanges((int) $menu->id),
        ];
    }
}
