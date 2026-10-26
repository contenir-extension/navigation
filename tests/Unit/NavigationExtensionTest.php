<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit;

use Contenir\Extension\Api\Handler\HandlerDeclaration;
use Contenir\Extension\Api\Registration;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Navigation\NavigationExtension;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function dirname;
use function is_file;

#[Group('unit')]
final class NavigationExtensionTest extends TestCase
{
    private static function registration(): Registration
    {
        $registry = new Registry(NavigationExtension::HANDLE);
        (new NavigationExtension())->register($registry);

        return $registry->registration();
    }

    #[Test]
    public function addsItsSectionToSitePagesWithItsTemplatesAndStyles(): void
    {
        $registration = self::registration();
        $root         = dirname(__DIR__, levels: 2);

        static::assertSame(
            ["{$root}/config/admin.xml", "{$root}/templates", "{$root}/public"],
            [$registration->module->adminXml, $registration->module->templates, $registration->module->assets],
        );
    }

    #[Test]
    public function guardsEachScreenWithThePrivilegeItNeeds(): void
    {
        static::assertSame(
            [
                'menus'           => 'view',
                'menu-add'        => 'add',
                'menu-edit'       => 'update',
                'menu-delete'     => 'delete',
                'item-add'        => 'add',
                'item-edit'       => 'update',
                'item-move'       => 'update',
                'item-delete'     => 'delete',
                'item-visibility' => 'update',
                'publish'         => 'approve',
                'history'         => 'view',
                'restore'         => 'update',
            ],
            array_map(
                static fn(HandlerDeclaration $handler): ?string => $handler->privilege?->value,
                self::registration()->handlers,
            ),
        );
    }

    #[Test]
    public function shipsTheMenuTreesScriptBuiltInItsAssets(): void
    {
        $module = self::registration()->module;
        $tree   = $module?->components[0] ?? null;

        static::assertSame(
            ['[data-navtree]', 'js/MenuTree.js', true],
            [$tree?->selector, $tree?->script, is_file("{$module?->assets}/{$tree?->script}")],
        );
    }
}
