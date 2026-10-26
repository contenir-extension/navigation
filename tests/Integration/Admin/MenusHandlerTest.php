<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Integration\Admin;

use Contenir\Extension\Api\Editor\Editor;
use Contenir\Extension\Navigation\Admin\MenusHandler;
use Contenir\Extension\Navigation\NavigationExtension;
use Contenir\Extension\Navigation\Tests\Trait\NavigationHostTrait;
use Contenir\Navigation\Link\RecordTarget;
use Contenir\Navigation\Link\Url;
use Contenir\Navigation\Menu\Display;
use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\Navigation;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_contains;

#[Group('integration')]
#[Group('handler')]
final class MenusHandlerTest extends TestCase
{
    use NavigationHostTrait;

    /**
     * Each row's label, icon, path, badge, drawing and children.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<list<mixed>>
     */
    private static function shape(array $rows): array
    {
        $shape = [];
        foreach ($rows as $row) {
            /** @var list<array<string, mixed>> $children */
            $children = $row['children'];
            $shape[]  = [
                $row['label'],
                $row['icon'],
                $row['path'],
                $row['badge'],
                $row['drawn'],
                self::shape($children),
            ];
        }

        return $shape;
    }

    #[Test]
    public function aMenuNeverPublishedIsNotLiveWithEveryItemAChange(): void
    {
        $menu = $this->menu();
        $this->item($menu, Item::text('Shop'));

        static::assertSame(
            ['live' => false, 'published' => null, 'by' => null, 'changes' => 1],
            $this->shown()['menu']['state'],
        );
    }

    #[Test]
    public function aPublishedMenuSaysWhenAndByWhomAndCountsLaterChanges(): void
    {
        $menu = $this->menu();
        $this->item($menu, Item::text('Shop'));
        $this->request(
            'publish',
            ['menu' => $menu],
            [NavigationExtension::SECTION],
            ['note' => ''],
            new Editor(4, 'Ann Editor'),
        );
        $this->item($menu, Item::text('Sale'), position: 1);

        static::assertSame(
            ['live' => true, 'published' => '23 Oct 2026, 9:00am', 'by' => 'Ann Editor', 'changes' => 1],
            $this->shown()['menu']['state'],
        );
    }

    #[Test]
    public function aRecordInASectionTheSiteNoLongerLinksIsMissing(): void
    {
        $this->setUpHostWithoutRecords();
        $menu = $this->menu();
        $this->item($menu, Item::record(new RecordTarget('page', 1)));

        static::assertSame(
            [['page 1', 'file', null, 'Missing', 'Label', []]],
            self::shape($this->shown()['menu']['tree']),
        );
    }

    #[Test]
    public function drawsEachKindOfItemWithItsLabelIconPathAndBadge(): void
    {
        $menu  = $this->menu();
        $about = $this->item($menu, Item::record(new RecordTarget('page', 1), 'staff'));
        $this->item($menu, Item::record(new RecordTarget('page', 2), label: 'Team'), $about->id);
        $this->item($menu, Item::record(new RecordTarget('news', 7)), position: 1);
        $this->item($menu, Item::record(new RecordTarget('page', 99), label: 'Gone'), position: 2);
        $this->item($menu, Item::url(Url::parse('hello@example.com'), 'Email us'), position: 3);
        $this->item(
            $menu,
            Item::text('Hours')->withPresentation('Hours', Display::IconLabel, 'clock', active: false),
            position: 4,
        );

        static::assertSame(
            [
                [
                    'About us',
                    'file',
                    '/about#staff',
                    null,
                    'Label',
                    [['Team', 'file', '/about/our-team', 'Draft', 'Label', []]],
                ],
                ['Launch', 'file', '/news/launch', 'Archived', 'Label', []],
                ['Gone', 'file', null, 'Missing', 'Label', []],
                ['Email us', 'mail', 'hello@example.com', null, 'Label', []],
                ['Hours', 'typography', null, 'Hidden', 'Icon "clock" with label', []],
            ],
            self::shape($this->shown()['menu']['tree']),
        );
    }

    #[Test]
    public function showsNoMenuWhenThereAreNone(): void
    {
        static::assertSame(['menu' => null], $this->shown());
    }

    #[Test]
    public function showsTheChosenMenusIdHandleAndDescription(): void
    {
        $menu = (int) $this->menus()->repository->saveNavigation(new Navigation('footer', 'Footer', 'At the foot'))->id;

        $shown = $this->shown()['menu'];

        static::assertSame([$menu, 'footer', 'At the foot'], [$shown['id'], $shown['handle'], $shown['description']]);
    }

    #[Test]
    public function showsTheFirstMenuUnlessAnotherIsNamed(): void
    {
        $this->menu('header', 'Header');
        $footer = $this->menu('footer', 'Footer');

        static::assertSame(
            ['Header', 'Footer'],
            [$this->shown()['menu']['title'], $this->shown(['menu' => $footer])['menu']['title']],
        );
    }

    #[Test]
    public function theScreenDrawsTheMenuAndItsItemsEscaped(): void
    {
        $menu = $this->menu('header', 'Header & top');
        $this->item($menu, Item::text('Fish & chips'));

        $body = (string) $this->request(MenusHandler::NAME)->getBody();

        static::assertSame(
            [true, true, true],
            [
                str_contains($body, 'Header &amp; top'),
                str_contains($body, 'Fish &amp; chips'),
                str_contains($body, 'Not live'),
            ],
        );
    }

    #[Test]
    public function theScreenIsGuardedByTheNavigationSection(): void
    {
        static::assertSame(
            [200, 403],
            [
                $this->request(MenusHandler::NAME, grants: [NavigationExtension::SECTION . ':view'])->getStatusCode(),
                $this->request(MenusHandler::NAME, grants: ['site'])->getStatusCode(),
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }

    /**
     * What the Navigation screen shows for $query.
     *
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     */
    private function shown(array $query = []): array
    {
        /** @var array<string, mixed> */
        return $this->answer(MenusHandler::class, $query)->template->values ?? [];
    }
}
