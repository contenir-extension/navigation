<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Persistence;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Editor\CurrentEditorInterface;
use Contenir\Extension\Api\Record\RecordsInterface;
use Contenir\Extension\Navigation\Event\MenuPostChange;
use Contenir\Navigation\Exception\InvalidItemException;
use Contenir\Navigation\Exception\NotFoundException;
use Contenir\Navigation\Exception\StorageException;
use Contenir\Navigation\Menu\Navigation;
use Contenir\Navigation\Menu\PublishedBy;
use Contenir\Navigation\Publishing\Publisher;
use Contenir\Navigation\Storage\NavigationRepositoryInterface;
use Contenir\Navigation\Storage\PhpDbNavigationRepository;
use Contenir\Navigation\Storage\Transaction;
use Contenir\Navigation\Storage\TransactionInterface;
use LogicException;
use Psr\Container\ContainerExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * What the add-on's screens work with: the site's menus and their publisher
 * over the site's database, the transaction they share, and, where the host
 * lends them, the site's records and the editor signed in. A change that
 * reaches the site is announced through the add-on's events, so the CMS clears
 * the site's caches.
 */
final readonly class Menus
{
    /**
     * @mago-expect lint:excessive-parameter-list One parameter per service the screens use; the host lends the last three.
     */
    public function __construct(
        public NavigationRepositoryInterface $repository,
        public Publisher $publisher,
        public TransactionInterface $transaction,
        public ?RecordsInterface $records = null,
        private ?CurrentEditorInterface $editors = null,
        private ?EventDispatcherInterface $events = null,
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
            $context->events(),
        );
    }

    /**
     * Announces that $menu's change has reached the site, when it is live there.
     */
    public function changed(Navigation $menu): void
    {
        if (null === $menu->revisionId) {
            return;
        }

        $this->events?->dispatch(new MenuPostChange($menu->handle));
    }

    /**
     * Publishes $menu's draft as a new revision, whether or not it was live, and announces it.
     *
     * @throws NotFoundException    When there is no such menu.
     * @throws InvalidItemException When the host's editor can't be recorded.
     * @throws StorageException     When the menu can't be read or published.
     */
    public function publish(int $menu): void
    {
        $this->publisher->publish($menu, $this->publisher());
        $this->changed($this->repository->navigation($menu));
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
        $navigation = $this->repository->navigation($menu);
        if (null === $navigation->revisionId) {
            return false;
        }

        $this->publisher->publish($menu, $this->publisher());
        $this->changed($navigation);

        return true;
    }
}
