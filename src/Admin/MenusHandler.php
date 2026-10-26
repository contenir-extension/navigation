<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Navigation\Persistence\Menus;
use Contenir\Navigation\Exception\NotFoundException;
use Contenir\Navigation\Exception\StorageException;
use Contenir\Navigation\Menu\Navigation;
use Contenir\Navigation\Menu\Tree;
use LogicException;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The Navigation section's home: the chosen menu's tree with where it stands
 * and what can be done with it. It shows the first menu unless the query
 * names another; Site Pages' section menu lists them all ({@see MenusMenu}).
 */
final class MenusHandler implements HandlerInterface
{
    public const string NAME = 'menus';

    /**
     * The menu with $id, or the first when there is none.
     *
     * @param list<Navigation> $menus
     */
    private static function chosen(array $menus, ?int $id): ?Navigation
    {
        foreach ($menus as $menu) {
            if ($id === $menu->id) {
                return $menu;
            }
        }

        return $menus[0] ?? null;
    }

    /**
     * @throws StorageException            When the menus can't be read.
     * @throws NotFoundException           When a menu's live revision is gone.
     * @throws ContainerExceptionInterface When the host can't make one of its services.
     * @throws LogicException              When the host offers no site database.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $menus  = Menus::of($context);
        $all    = $menus->repository->navigations();
        $id     = Input::of($request)->id('menu');
        $chosen = self::chosen($all, $id);

        return HandlerResult::template('navigation/menus', 'Navigation', [
            'menu' => null === $chosen
                ? null
                : [
                    'id'          => $chosen->id,
                    'title'       => $chosen->title,
                    'handle'      => $chosen->handle,
                    'description' => $chosen->description,
                    'state'       => MenuState::of(
                        $chosen,
                        $menus->repository,
                        $menus->publisher,
                        new SiteTime($context->clock()),
                    ),
                    'tree'        => (new TreeRows($menus->records))->rows(
                        Tree::build($menus->repository->items((int) $chosen->id)),
                    ),
                ],
        ]);
    }
}
