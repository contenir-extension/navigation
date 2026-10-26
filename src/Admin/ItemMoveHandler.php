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
 * Moves an item in a menu's draft, when posted: one place up or down among
 * its siblings, or under the parent and at the position posted, as dragging
 * it in the tree does.
 */
final class ItemMoveHandler implements HandlerInterface
{
    public const string NAME = 'item-move';

    public const string UP = 'up';

    public const string DOWN = 'down';

    /**
     * @throws StorageException            When the item can't be read or moved.
     * @throws ContainerExceptionInterface When the host can't make one of its services.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $input = Input::of($request);
        if ('POST' !== $request->getMethod()) {
            return HandlerResult::redirect(Urls::menu(null));
        }

        $menus = Menus::of($context);

        try {
            $item = $menus->repository->item((int) $input->id('item'));
            [$parent, $position] = match ($input->posted('direction')) {
                self::UP => [$item->parentId, $item->sequence - 1],
                self::DOWN => [$item->parentId, $item->sequence + 1],
                default => [$input->id('parent'), (int) $input->posted('position')],
            };
            $menus->repository->moveItem((int) $item->id, $parent, $position);
            $menus->publishIfLive((int) $item->navigationId);
        } catch (NotFoundException) {
            return HandlerResult::redirect(Urls::menu(null))->withMessage('warning', 'That item no longer exists.');
        } catch (InvalidItemException $problem) {
            return HandlerResult::redirect(Urls::menu($item->navigationId))->withMessage(
                'danger',
                $problem->getMessage(),
            );
        }

        return HandlerResult::redirect(Urls::menu($item->navigationId));
    }
}
