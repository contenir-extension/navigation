<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Integration\Admin;

use Contenir\Extension\Api\Module\MenuGroup;
use Contenir\Extension\Api\Module\MenuItem;
use Contenir\Extension\Navigation\Admin\MenusMenu;
use Contenir\Extension\Navigation\Tests\Trait\NavigationHostTrait;
use Contenir\Navigation\Menu\Item;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_map;
use function array_values;

#[Group('integration')]
#[Group('menu')]
final class MenusMenuTest extends TestCase
{
    use NavigationHostTrait;

    /**
     * @return array<string, array{string, array<string, scalar>, list<string>}>
     */
    public static function currentProvider(): array
    {
        return [
            'the Navigation screen naming none' => ['/menus', [], ['Header']],
            'the Navigation screen naming one'  => ['/menus', ['menu' => 2], ['Footer']],
            'a screen naming a menu'            => ['/history', ['menu' => 2], ['Footer']],
            'a screen naming an item'           => ['/item-edit', ['item' => 2], ['Footer']],
            'a screen naming a missing item'    => ['/item-edit', ['item' => 99], []],
            'a screen naming neither'           => ['/menu-add', [], []],
        ];
    }

    #[Test]
    public function listsEachMenuUnderNavigationWithHowManyItemsItHolds(): void
    {
        $header = $this->menu('header', 'Header');
        $footer = $this->menu('footer', 'Footer');
        $this->item($header, Item::text('Shop'));
        $this->item($footer, Item::text('Terms'));
        $this->item($footer, Item::text('Privacy'), position: 1);

        $groups = $this->listed('/menus');

        static::assertSame(
            [
                'Navigation',
                [
                    ['Header', 'menus', ['menu' => $header], 1],
                    ['Footer', 'menus', ['menu' => $footer], 2],
                ],
            ],
            [
                $groups[0]->title ?? null,
                array_map(
                    static fn(MenuItem $item): array => [$item->label, $item->handler, $item->query, $item->count],
                    $groups[0]->items ?? [],
                ),
            ],
        );
    }

    #[Test]
    public function listsNothingWithoutMenusSoTheHostKeepsTheSection(): void
    {
        static::assertSame([], $this->listed('/menus'));
    }

    /**
     * @param array<string, scalar> $query
     * @param list<string>          $current
     */
    #[Test]
    #[DataProvider('currentProvider')]
    public function marksTheMenuTheScreenIsAboutCurrent(string $path, array $query, array $current): void
    {
        $this->menu('header', 'Header');
        $footer = $this->menu('footer', 'Footer');
        $this->item($footer, Item::text('Terms'));
        $this->item($footer, Item::text('Privacy'), position: 1);

        $items = $this->listed($path, $query)[0]->items ?? [];

        static::assertSame(
            $current,
            array_values(array_map(
                static fn(MenuItem $item): string => $item->label,
                array_filter($items, static fn(MenuItem $item): bool => $item->current),
            )),
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return list<MenuGroup>
     */
    private function listed(string $path, array $query = []): array
    {
        $request = $this->http
            ->createServerRequest('GET', "/admin/extension/navigation{$path}")
            ->withQueryParams($query);

        return (new MenusMenu())->groups($request, $this->context());
    }
}
