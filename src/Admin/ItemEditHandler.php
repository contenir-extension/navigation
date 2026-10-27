<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\LinkRejectedException;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\ParentPage;
use Contenir\Extension\Api\Handler\Screen;
use Contenir\Extension\Navigation\Persistence\Menus;
use Contenir\Navigation\Exception\InvalidItemException;
use Contenir\Navigation\Exception\InvalidLinkException;
use Contenir\Navigation\Exception\NotFoundException;
use Contenir\Navigation\Exception\StorageException;
use Contenir\Navigation\Menu\Item;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Changes an item in a menu's draft: what it links to, its label, how it is
 * drawn and where it sits. Moving it under another item puts it last there.
 */
final class ItemEditHandler implements HandlerInterface
{
    public const string NAME = 'item-edit';

    private static function screen(Item $item, Form $form): HandlerResult
    {
        return HandlerResult::screen(new Screen(
            'Edit item',
            'Changes go into the menu\'s draft; the site shows them once the menu is published.',
            $form,
            parent: new ParentPage('Navigation', MenusHandler::NAME, ['menu' => (int) $item->navigationId]),
        ));
    }

    /**
     * @throws StorageException            When the item can't be read or saved.
     * @throws InvalidDeclarationException Never: the form's fields are fixed.
     * @throws ContainerExceptionInterface When the host can't make one of its services.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $input = Input::of($request);
        $menus = Menus::of($context);

        try {
            $item = $menus->repository->item((int) $input->id('item'));
        } catch (NotFoundException) {
            return HandlerResult::redirect(Urls::menu(null))->withMessage('warning', 'That item no longer exists.');
        }

        $menu   = (int) $item->navigationId;
        $items  = $menus->repository->items($menu);
        $posted = new ItemInput($input);
        $parent = 'POST' === $request->getMethod() ? $posted->parent() : $item->parentId;
        $under  = Placement::item($items, $parent);
        $form   = /** @param array<string, scalar|null> $values */ static fn(array $values): Form => ItemForm::editing(
            ItemChoices::parents($items, $item->id),
            $values,
            null !== $menus->records,
        );
        if ('POST' !== $request->getMethod()) {
            return self::screen($item, $form(ItemValues::of($item)));
        }

        try {
            $live = $menus->transaction->run(
                /**
                 * @throws InvalidItemException
                 * @throws InvalidLinkException
                 * @throws LinkRejectedException
                 * @throws NotFoundException
                 * @throws PageRefusedException
                 * @throws StorageException
                 */
                static function () use ($menus, $posted, $parent, $under, $items, $item): bool {
                    $changed = $posted->presented(
                        $posted->item($item, NewPage::made($menus->records, $posted->link(), $under)),
                    );
                    $saved = $menus->repository->saveItem($changed);
                    if ($parent !== $item->parentId) {
                        $menus->repository->moveItem((int) $saved->id, $parent, Placement::last($items, $parent));
                    }

                    return $menus->publishIfLive((int) $item->navigationId);
                },
            );
        } catch (
            InvalidItemException|InvalidLinkException|LinkRejectedException|NotFoundException|PageRefusedException $problem
        ) {
            return self::screen($item, $form(ItemValues::posted($input)))
                ->withMessage('danger', $problem->getMessage());
        }

        return HandlerResult::redirect(Urls::menu($menu))->withMessage(
            'success',
            $live ? 'Item saved.' : 'Item saved to the draft.',
        );
    }
}
