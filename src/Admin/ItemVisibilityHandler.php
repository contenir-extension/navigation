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
 * Shows or hides an item, when posted, as the switch on its row in the menu's tree says: shown
 * when "active" is ticked. A live menu shows the change at once.
 */
final class ItemVisibilityHandler implements HandlerInterface
{
    public const string NAME = 'item-visibility';

    /**
     * @throws StorageException            When the item can't be read or saved.
     * @throws ContainerExceptionInterface When the host can't make one of its services.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        if ('POST' !== $request->getMethod()) {
            return HandlerResult::redirect(Urls::menu(null));
        }

        $input = Input::of($request);
        $menus = Menus::of($context);

        try {
            $item = $menus->repository->item((int) $input->id('item'));
            $menus->repository->saveItem($item->withPresentation(
                $item->label,
                $item->display,
                $item->icon,
                $item->target,
                $item->class,
                $input->checked('active'),
            ));
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
