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
 * Returns a menu's draft to its live revision, when posted.
 */
final class DiscardHandler implements HandlerInterface
{
    public const string NAME = 'discard';

    /**
     * @throws StorageException            When the draft can't be replaced.
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

        try {
            $discarded = Menus::of($context)->publisher->discard($id);
        } catch (NotFoundException) {
            return HandlerResult::redirect(Urls::menu(null))->withMessage('warning', 'That menu no longer exists.');
        }

        return $discarded
            ? HandlerResult::redirect(Urls::menu($id))->withMessage('success', 'Changes discarded.')
            : HandlerResult::redirect(Urls::menu($id))->withMessage(
                'warning',
                'The menu has never been published, so there is nothing to go back to.',
            );
    }
}
