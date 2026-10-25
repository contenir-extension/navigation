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

/**
 * Renames a menu, changes its handle or says where it appears.
 */
final class MenuEditHandler implements HandlerInterface
{
    public const string NAME = 'menu-edit';

    /**
     * @param array{title?: string, handle?: string, description?: string|null} $values
     *
     * @throws InvalidDeclarationException Never: the form's fields are fixed.
     */
    private static function screen(Navigation $menu, array $values, ?string $shared): HandlerResult
    {
        return HandlerResult::screen(new Screen(
            "Edit {$menu->title}",
            'Changing the handle changes what the site\'s templates ask for, so do it with the templates.',
            MenuForm::of($values, $shared),
            parent: new ParentPage('Navigation', MenusHandler::NAME, ['menu' => (int) $menu->id]),
        ));
    }

    /**
     * @throws StorageException            When the menu can't be read or saved.
     * @throws NotFoundException           When the menu is deleted while it is being saved.
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

        if ('POST' !== $request->getMethod()) {
            return self::screen(
                $menu,
                [
                    'title'       => $menu->title,
                    'handle'      => $menu->handle,
                    'description' => $menu->description,
                ],
                MenuForm::shared($menus->repository, $menu->handle, $menu->id),
            );
        }

        try {
            $saved = $menus->repository->saveNavigation(MenuForm::posted($input, $menu));
        } catch (InvalidItemException $problem) {
            $values = MenuForm::values($input);

            return self::screen($menu, $values, MenuForm::shared($menus->repository, $values['handle'], $menu->id))
                ->withMessage('danger', $problem->getMessage());
        }

        $shared = MenuForm::shared($menus->repository, $saved->handle, $saved->id);

        return HandlerResult::redirect(Urls::menu($menu->id))->withMessage(
            null === $shared ? 'success' : 'warning',
            null === $shared ? 'Menu saved.' : "Menu saved. {$shared}",
        );
    }
}
