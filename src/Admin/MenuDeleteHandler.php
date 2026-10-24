<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Navigation\Persistence\Menus;
use Contenir\Navigation\Exception\StorageException;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Deletes a menu with its items and revisions, when posted. The records its
 * items link to are not touched.
 */
final class MenuDeleteHandler implements HandlerInterface
{
    public const string NAME = 'menu-delete';

    /**
     * @throws StorageException            When the menu can't be deleted.
     * @throws ContainerExceptionInterface When the host can't make one of its services.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $id = Input::of($request)->id('menu');
        if ('POST' !== $request->getMethod() || null === $id) {
            return HandlerResult::redirect(Urls::menu($id));
        }

        Menus::of($context)->repository->deleteNavigation($id);

        return HandlerResult::redirect(Urls::menu(null))->withMessage('success', 'Menu deleted.');
    }
}
