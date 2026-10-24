<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\ParentPage;
use Contenir\Extension\Api\Handler\Screen;
use Contenir\Extension\Navigation\Persistence\Menus;
use Contenir\Navigation\Exception\InvalidItemException;
use Contenir\Navigation\Exception\NotFoundException;
use Contenir\Navigation\Exception\StorageException;
use Contenir\Navigation\Menu\Navigation;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function count;

/**
 * Adds a menu, after the others.
 */
final class MenuAddHandler implements HandlerInterface
{
    public const string NAME = 'menu-add';

    /**
     * @param array{title?: string, handle?: string, description?: string|null} $values
     *
     * @throws InvalidDeclarationException Never: the form's fields are fixed.
     */
    private static function screen(array $values): HandlerResult
    {
        return HandlerResult::screen(new Screen(
            'Add menu',
            'A menu is a list of links the site draws in one place, such as its header or footer.',
            MenuForm::of($values),
            parent: new ParentPage('Navigation', MenusHandler::NAME),
        ));
    }

    /**
     * @throws StorageException            When the menu can't be saved.
     * @throws NotFoundException           Never: a new menu is read back as it is saved.
     * @throws InvalidDeclarationException Never: the form's fields are fixed.
     * @throws ContainerExceptionInterface When the host can't make one of its services.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        if ('POST' !== $request->getMethod()) {
            return self::screen([]);
        }

        $input = Input::of($request);
        $menus = Menus::of($context);

        try {
            $posted = MenuForm::posted($input);
            $menu   = $menus->repository->saveNavigation(new Navigation(
                $posted->handle,
                $posted->title,
                $posted->description,
                count($menus->repository->navigations()),
            ));
        } catch (InvalidItemException $problem) {
            return self::screen(MenuForm::values($input))->withMessage('danger', $problem->getMessage());
        }

        return HandlerResult::redirect(Urls::menu($menu->id))->withMessage('success', 'Menu added.');
    }
}
