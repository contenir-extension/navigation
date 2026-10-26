<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Navigation\Persistence\Menus;
use Contenir\Navigation\Exception\InvalidItemException;
use Contenir\Navigation\Exception\NotFoundException;
use Contenir\Navigation\Exception\StorageException;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Deletes an item from a menu's draft, with everything nested under it,
 * when posted. The record it links to is not touched.
 */
final class ItemDeleteHandler implements HandlerInterface
{
    public const string NAME = 'item-delete';

    /**
     * @throws StorageException            When the item can't be read or deleted.
     * @throws ContainerExceptionInterface When the host can't make one of its services.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        if ('POST' !== $request->getMethod()) {
            return HandlerResult::redirect(Urls::menu(null));
        }

        $menus = Menus::of($context);

        try {
            $item = $menus->repository->item((int) Input::of($request)->id('item'));
            $menus->repository->deleteItem((int) $item->id);
            $live = $menus->publishIfLive((int) $item->navigationId);
        } catch (NotFoundException) {
            return HandlerResult::redirect(Urls::menu(null))->withMessage('warning', 'That item no longer exists.');
        } catch (InvalidItemException $problem) {
            return HandlerResult::redirect(Urls::menu($item->navigationId))->withMessage(
                'danger',
                $problem->getMessage(),
            );
        }

        return HandlerResult::redirect(Urls::menu($item->navigationId))->withMessage(
            'success',
            $live ? 'Item deleted.' : 'Item deleted from the draft.',
        );
    }
}
