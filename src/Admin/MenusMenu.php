<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Module\MenuGroup;
use Contenir\Extension\Api\Module\MenuInterface;
use Contenir\Extension\Api\Module\MenuItem;
use Contenir\Extension\Navigation\Persistence\Menus;
use Contenir\Navigation\Exception\NotFoundException;
use Contenir\Navigation\Exception\StorageException;
use Contenir\Navigation\Menu\Navigation;
use Contenir\Navigation\Storage\NavigationRepositoryInterface;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function count;
use function str_ends_with;

/**
 * The Navigation section's part of Site Pages' section menu: each of the
 * site's menus, with how many items its draft holds, leading to its tree, in
 * place of the one Menus section. The menu a screen is about is current, and
 * the first on the Navigation screen when it names none. With no menus yet
 * it lists nothing, so the host keeps Menus, which offers Add menu.
 */
final class MenusMenu implements MenuInterface
{
    public const string TITLE = 'Navigation';

    /**
     * The menu $request is about: the one it names, the one holding the item
     * it names, or $first on the Navigation screen.
     *
     * @throws StorageException When the item can't be read.
     */
    private static function current(
        ServerRequestInterface $request,
        NavigationRepositoryInterface $repository,
        Navigation $first,
    ): ?int {
        $input = Input::of($request);
        $item  = $input->id('item');
        if (null !== $item) {
            try {
                return $repository->item($item)->navigationId;
            } catch (NotFoundException) {
                return null;
            }
        }

        return (
            $input->id('menu')
                ?? (str_ends_with($request->getUri()->getPath(), '/' . MenusHandler::NAME) ? $first->id : null)
        );
    }

    /**
     * @throws StorageException            When the menus can't be read.
     * @throws ContainerExceptionInterface When the host can't make one of its services.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function groups(ServerRequestInterface $request, ExtensionContextInterface $context): array
    {
        $repository = Menus::of($context)->repository;
        $menus      = $repository->navigations();
        if ([] === $menus) {
            return [];
        }

        $current = self::current($request, $repository, $menus[0]);
        $items   = [];
        foreach ($menus as $menu) {
            $id      = (int) $menu->id;
            $items[] = new MenuItem(
                $menu->title,
                MenusHandler::NAME,
                ['menu' => $id],
                $id === $current,
                count($repository->items($id)),
            );
        }

        return [new MenuGroup(self::TITLE, $items)];
    }
}
