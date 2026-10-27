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
use Contenir\Navigation\Menu\Navigation;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Adds an item to a menu's draft, last among the items it sits under. The
 * query names the menu, and may name the item to add it under.
 */
final class ItemAddHandler implements HandlerInterface
{
    public const string NAME = 'item-add';

    private static function screen(Navigation $menu, Form $form): HandlerResult
    {
        return HandlerResult::screen(new Screen(
            "Add an item to {$menu->title}",
            'The item goes into the menu\'s draft; the site shows it once the menu is published.',
            $form,
            parent: new ParentPage('Navigation', MenusHandler::NAME, ['menu' => (int) $menu->id]),
        ));
    }

    /**
     * @throws StorageException            When the menu can't be read or the item saved.
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
            $menu = $menus->repository->navigation((int) $input->id('menu'));
        } catch (NotFoundException) {
            return HandlerResult::redirect(Urls::menu(null))->withMessage('warning', 'That menu no longer exists.');
        }

        $items  = $menus->repository->items((int) $menu->id);
        $posted = new ItemInput($input);
        $parent = $posted->parent();
        $under  = Placement::item($items, $parent);
        $form   = /** @param array<string, scalar|null> $values */ static fn(array $values): Form => ItemForm::adding(
            ItemChoices::parents($items),
            $values,
            null !== $menus->records,
        );
        if ('POST' !== $request->getMethod()) {
            return self::screen($menu, $form(['parent' => (string) $parent]));
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
                static function () use ($menus, $menu, $posted, $parent, $under, $items): bool {
                    $item = $posted->item(null, NewPage::made($menus->records, $posted->link(), $under))
                        ->withPlacement((int) $menu->id, $parent, Placement::last($items, $parent));
                    $menus->repository->saveItem($item);

                    return $menus->publishIfLive((int) $menu->id);
                },
            );
        } catch (
            InvalidItemException|InvalidLinkException|LinkRejectedException|NotFoundException|PageRefusedException $problem
        ) {
            return self::screen($menu, $form(ItemValues::posted($input)))
                ->withMessage('danger', $problem->getMessage());
        }

        return HandlerResult::redirect(Urls::menu($menu->id))->withMessage(
            'success',
            $live ? 'Item added.' : 'Item added to the draft.',
        );
    }
}
