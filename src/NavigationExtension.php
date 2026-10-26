<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation;

use Contenir\Extension\Api\ExtensionInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\Privilege;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Navigation\Admin\HistoryHandler;
use Contenir\Extension\Navigation\Admin\ItemAddHandler;
use Contenir\Extension\Navigation\Admin\ItemDeleteHandler;
use Contenir\Extension\Navigation\Admin\ItemEditHandler;
use Contenir\Extension\Navigation\Admin\ItemMoveHandler;
use Contenir\Extension\Navigation\Admin\ItemVisibilityHandler;
use Contenir\Extension\Navigation\Admin\MenuAddHandler;
use Contenir\Extension\Navigation\Admin\MenuDeleteHandler;
use Contenir\Extension\Navigation\Admin\MenuEditHandler;
use Contenir\Extension\Navigation\Admin\MenusHandler;
use Contenir\Extension\Navigation\Admin\MenusMenu;
use Contenir\Extension\Navigation\Admin\PublishHandler;
use Contenir\Extension\Navigation\Admin\RestoreHandler;
use Override;

use function dirname;

/**
 * Managed site navigation: the Navigation section of Site Pages, where
 * editors build the site's menus as drafts, publish them, and restore older
 * revisions. Site Pages' section menu lists each menu in the section's place. Every screen is guarded by that section. The navigation tables
 * belong to contenir/contenir-navigation, whose migrations the CMS runs
 * against the site's database.
 */
final readonly class NavigationExtension implements ExtensionInterface
{
    public const string HANDLE = 'navigation';

    /**
     * The section of Site Pages its admin.xml adds, which guards its screens.
     */
    public const string SECTION = 'site.navigation.menus';

    /**
     * Each screen, with the privilege it needs in the section. Publishing
     * needs Approve, so a role can edit a menu's draft without making it
     * live.
     *
     * @var array<string, array{class-string<HandlerInterface>, Privilege}>
     */
    public const array SCREENS = [
        MenusHandler::NAME          => [MenusHandler::class, Privilege::View],
        MenuAddHandler::NAME        => [MenuAddHandler::class, Privilege::Add],
        MenuEditHandler::NAME       => [MenuEditHandler::class, Privilege::Update],
        MenuDeleteHandler::NAME     => [MenuDeleteHandler::class, Privilege::Delete],
        ItemAddHandler::NAME        => [ItemAddHandler::class, Privilege::Add],
        ItemEditHandler::NAME       => [ItemEditHandler::class, Privilege::Update],
        ItemMoveHandler::NAME       => [ItemMoveHandler::class, Privilege::Update],
        ItemDeleteHandler::NAME     => [ItemDeleteHandler::class, Privilege::Delete],
        ItemVisibilityHandler::NAME => [ItemVisibilityHandler::class, Privilege::Update],
        PublishHandler::NAME        => [PublishHandler::class, Privilege::Approve],
        HistoryHandler::NAME        => [HistoryHandler::class, Privilege::View],
        RestoreHandler::NAME        => [RestoreHandler::class, Privilege::Update],
    ];

    /**
     * The menu tree's lists, which MenuTree, built into the add-on's assets,
     * lets an editor drag items between.
     */
    public const string TREE_COMPONENT = '[data-navtree]';

    #[Override]
    public function register(Registry $registry): void
    {
        $root = dirname(__DIR__);
        $registry->module("{$root}/config/admin.xml");
        $registry->menu(MenusMenu::class);
        $registry->templates("{$root}/templates");
        $registry->assets("{$root}/public");
        $registry->component(self::TREE_COMPONENT, 'js/MenuTree.js');

        foreach (self::SCREENS as $name => [$class, $privilege]) {
            $registry->handler($name, $class, $privilege);
        }
    }
}
