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
 * Puts a menu on the site, when posted, as a revision recording who
 * published it. A menu is published once: after that every change to it
 * publishes itself as it is saved.
 */
final class PublishHandler implements HandlerInterface
{
    public const string NAME = 'publish';

    /**
     * @throws StorageException            When the menu can't be published.
     * @throws ContainerExceptionInterface When the host can't make one of its services.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $input = Input::of($request);
        $id    = $input->id('menu');
        if ('POST' !== $request->getMethod() || null === $id) {
            return HandlerResult::redirect(Urls::menu($id));
        }

        $menus = Menus::of($context);

        try {
            $menus->publisher->publish($id, $menus->publisher());
        } catch (NotFoundException) {
            return HandlerResult::redirect(Urls::menu(null))->withMessage('warning', 'That menu no longer exists.');
        } catch (InvalidItemException $problem) {
            return HandlerResult::redirect(Urls::menu($id))->withMessage('danger', $problem->getMessage());
        }

        return HandlerResult::redirect(Urls::menu($id))->withMessage('success', 'Menu published.');
    }
}
