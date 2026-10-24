<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Navigation\Persistence\Menus;
use Contenir\Navigation\Exception\NotFoundException;
use Contenir\Navigation\Exception\StorageException;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Copies a revision into its menu's draft, when posted, ready to publish.
 */
final class RestoreHandler implements HandlerInterface
{
    public const string NAME = 'restore';

    /**
     * @throws StorageException            When the revision can't be read or the draft replaced.
     * @throws ContainerExceptionInterface When the host can't make one of its services.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $id = Input::of($request)->id('revision');
        if ('POST' !== $request->getMethod() || null === $id) {
            return HandlerResult::redirect(Urls::menu(null));
        }

        $menus = Menus::of($context);

        try {
            $revision = $menus->repository->revision($id);
            $menus->publisher->restore($id);
        } catch (NotFoundException) {
            return HandlerResult::redirect(Urls::menu(null))->withMessage('warning', 'That revision no longer exists.');
        }

        return HandlerResult::redirect(Urls::menu($revision->navigationId))->withMessage(
            'success',
            'Revision restored into the draft. Publish the menu to make it live.',
        );
    }
}
