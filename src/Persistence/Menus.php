<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Persistence;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Editor\CurrentEditorInterface;
use Contenir\Extension\Api\Record\RecordsInterface;
use Contenir\Navigation\Exception\InvalidItemException;
use Contenir\Navigation\Exception\NotFoundException;
use Contenir\Navigation\Exception\StorageException;
use Contenir\Navigation\Menu\PublishedBy;
use Contenir\Navigation\Publishing\Publisher;
use Contenir\Navigation\Storage\NavigationRepositoryInterface;
use Contenir\Navigation\Storage\PhpDbNavigationRepository;
use Contenir\Navigation\Storage\Transaction;
use Contenir\Navigation\Storage\TransactionInterface;
use LogicException;
use Psr\Container\ContainerExceptionInterface;

/**
 * What the add-on's screens work with: the site's menus and their publisher
 * over the site's database, the transaction they share, and, where the host
 * lends them, the site's records and the editor signed in.
 */
final readonly class Menus
{
    public function __construct(
        public NavigationRepositoryInterface $repository,
        public Publisher $publisher,
        public TransactionInterface $transaction,
        public ?RecordsInterface $records = null,
        private ?CurrentEditorInterface $editors = null,
    ) {}

    /**
     * @throws LogicException              When the host offers no site database.
     * @throws ContainerExceptionInterface When the host can't make one of its services.
     */
    public static function of(ExtensionContextInterface $context): self
    {
        $adapter     = SiteDatabase::of($context);
        $services    = $context->services();
        $repository  = new PhpDbNavigationRepository($adapter, $context->clock());
        $transaction = new Transaction($adapter);
        $records     = $services->has(RecordsInterface::class) ? $services->get(RecordsInterface::class) : null;
        $editors     = $services->has(CurrentEditorInterface::class)
            ? $services->get(CurrentEditorInterface::class)
            : null;

        return new self(
            $repository,
            new Publisher($repository, $transaction, $context->clock()),
            $transaction,
            $records instanceof RecordsInterface ? $records : null,
            $editors instanceof CurrentEditorInterface ? $editors : null,
        );
    }

    /**
     * Who is publishing, when the host says who is signed in.
     *
     * @throws InvalidItemException When the host's editor can't be recorded, such as an id longer than 64 characters.
     */
    public function publisher(): ?PublishedBy
    {
        $editor = $this->editors?->current();

        return null === $editor ? null : new PublishedBy($editor->id, $editor->name);
    }

    /**
     * Publishes $menu's draft as a new revision when the menu is live, so a change to a live menu
     * reaches the site as it is saved. A menu not yet published stays a draft until someone
     * publishes it. Says whether it published.
     *
     * @throws NotFoundException    When there is no such menu.
     * @throws InvalidItemException When the host's editor can't be recorded.
     * @throws StorageException     When the menu can't be read or published.
     */
    public function publishIfLive(int $menu): bool
    {
        if (null === $this->repository->navigation($menu)->revisionId) {
            return false;
        }

        $this->publisher->publish($menu, $this->publisher());

        return true;
    }
}
