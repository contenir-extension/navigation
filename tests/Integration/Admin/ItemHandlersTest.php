<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Integration\Admin;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Navigation\Admin\ItemAddHandler;
use Contenir\Extension\Navigation\Admin\ItemDeleteHandler;
use Contenir\Extension\Navigation\Admin\ItemEditHandler;
use Contenir\Extension\Navigation\Admin\ItemMoveHandler;
use Contenir\Extension\Navigation\Admin\ItemVisibilityHandler;
use Contenir\Extension\Navigation\Tests\Trait\NavigationHostTrait;
use Contenir\Navigation\Link\RecordTarget;
use Contenir\Navigation\Link\Url;
use Contenir\Navigation\Menu\Display;
use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\ItemKind;
use Contenir\Navigation\Menu\Node;
use Contenir\Navigation\Menu\Tree;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_flip;
use function array_intersect_key;
use function array_map;
use function array_values;
use function trim;

#[Group('integration')]
#[Group('handler')]
final class ItemHandlersTest extends TestCase
{
    use NavigationHostTrait;

    private int $menu;

    /**
     * @return array<string, array{array<string, string>, bool}>
     */
    public static function visibilityProvider(): array
    {
        return [
            'switched off' => [[], false],
            'switched on'  => [['active' => '1'], true],
        ];
    }

    /**
     * What the link field posts for a new page at $path.
     *
     * @return array<string, string>
     */
    private static function newPage(string $path): array
    {
        return [
            'kind'     => 'new',
            'section'  => 'page',
            'title'    => 'Our story',
            'path'     => $path,
            'workflow' => 'standard',
        ];
    }

    #[Test]
    public function addingAnAddressAMenuCannotHoldSaysWhy(): void
    {
        $result = $this->answer(
            ItemAddHandler::class,
            ['menu' => $this->menu],
            [
                'link'  => ['kind' => 'address', 'address' => 'javascript:alert(1)'],
                'label' => 'x',
            ],
        );

        static::assertSame('A menu can\'t link to a javascript: address', $result->messages[0]['text'] ?? null);
    }

    #[Test]
    public function addingAnAddressOrTextNeedsALabel(): void
    {
        $result = $this->answer(
            ItemAddHandler::class,
            ['menu' => $this->menu],
            ['link' => ['kind' => 'address', 'address' => 'hello@example.com']],
        );

        static::assertSame(
            ['A URL item links to an address alone, with a label', 'address:hello@example.com', []],
            [$result->messages[0]['text'] ?? null, $this->field($result, 'link')?->default, $this->tree()],
        );
    }

    #[Test]
    public function addingAnItemOffersCreateNewUnderItsParentsPageAndTheItemsItCanGoUnder(): void
    {
        $about  = $this->item($this->menu, Item::record(new RecordTarget('page', 1)));
        $result = $this->answer(ItemAddHandler::class, ['menu' => $this->menu, 'parent' => (int) $about->id]);

        static::assertSame(
            [
                ['create' => true, 'prefix' => '/about', 'label' => 'label'],
                ['' => 'None (top level)', '1' => '— page 1'],
                '1',
            ],
            [
                $this->field($result, 'link')?->attributes,
                $this->field($result, 'parent')?->options,
                $this->field($result, 'parent')?->default,
            ],
        );
    }

    #[Test]
    public function addingAnItemPutsItLastUnderItsParent(): void
    {
        $shop = $this->item($this->menu, Item::text('Shop'));
        $this->item($this->menu, Item::text('Sale'), $shop->id);

        $result = $this->answer(
            ItemAddHandler::class,
            ['menu' => $this->menu],
            [
                'link'   => ['kind' => 'record', 'record' => 'page:1', 'fragment' => '#staff'],
                'parent' => (string) $shop->id,
            ],
        );

        static::assertEquals(
            [
                '/admin/extension/navigation/menus?menu=1',
                [['level' => 'success', 'text' => 'Item added to the draft.']],
                [['Shop', [['Sale', []], ['page 1 #staff', []]]]],
            ],
            [$result->url, $result->messages, $this->tree()],
        );
    }

    #[Test]
    public function addingAnItemToAMenuThatIsGoneGoesBackToNavigation(): void
    {
        static::assertSame(
            '/admin/extension/navigation/menus',
            $this->answer(ItemAddHandler::class, ['menu' => 9])->url,
        );
    }

    #[Test]
    public function addingAnItemUnderOneThatIsGoneSaysSo(): void
    {
        $result = $this->answer(
            ItemAddHandler::class,
            ['menu' => $this->menu],
            ['label' => 'Shop', 'parent' => '99'],
        );

        static::assertSame('There is no menu item 99', $result->messages[0]['text'] ?? null);
    }

    #[Test]
    public function addingAnItemUnderTextStartsNewPagesAtTheTop(): void
    {
        $shop   = $this->item($this->menu, Item::text('Shop'));
        $result = $this->answer(ItemAddHandler::class, ['menu' => $this->menu, 'parent' => (int) $shop->id]);

        static::assertSame(
            ['create' => true, 'prefix' => '', 'label' => 'label'],
            $this->field($result, 'link')?->attributes,
        );
    }

    #[Test]
    public function addingAnItemWithANewPageMakesTheDraftUnderItsParentsPageAndLinksToIt(): void
    {
        $about  = $this->item($this->menu, Item::record(new RecordTarget('page', 1)));
        $result = $this->answer(
            ItemAddHandler::class,
            ['menu' => $this->menu],
            [
                'link'   => self::newPage('/about/our-story'),
                'parent' => (string) $about->id,
            ],
        );

        static::assertSame(
            [
                'Item added to the draft.',
                [['page 1', [['page 3', []]]]],
                ['Our story', '/about/our-story', 'draft', 'standard', '1'],
            ],
            [
                $result->messages[0]['text'] ?? null,
                $this->tree(),
                array_values(array_intersect_key(
                    $this->host->stored('page', 3) ?? [],
                    array_flip(['title', 'path', 'status', 'workflow', 'parent']),
                )),
            ],
        );
    }

    #[Test]
    public function addingAnItemWithANewPageTheSiteRefusesSaysWhyAndAddsNothing(): void
    {
        $result = $this->answer(ItemAddHandler::class, ['menu' => $this->menu], ['link' => self::newPage('/about')]);

        static::assertSame(
            ['Some fields need attention That path is already in use', []],
            [$result->messages[0]['text'] ?? null, $this->tree()],
        );
    }

    #[Test]
    public function addingARecordItemNeedsARecord(): void
    {
        $result = $this->answer(
            ItemAddHandler::class,
            ['menu' => $this->menu],
            ['link' => ['kind' => 'record', 'record' => '']],
        );

        static::assertSame('Choose the page or record to link to', $result->messages[0]['text'] ?? null);
    }

    #[Test]
    public function addingTextKeepsItsHtmlSanitised(): void
    {
        $this->answer(
            ItemAddHandler::class,
            ['menu' => $this->menu],
            [
                'label' => 'Hours',
                'html'  => '<strong>Open</strong><script>x</script>',
            ],
        );

        static::assertSame('<strong>Open</strong>', $this->menus()->repository->items($this->menu)[0]->html);
    }

    #[Test]
    public function deletingAnItemDeletesWhatIsUnderIt(): void
    {
        $shop = $this->item($this->menu, Item::text('Shop'));
        $this->item($this->menu, Item::text('Sale'), $shop->id);
        $this->item($this->menu, Item::text('News'), position: 1);

        $result = $this->answer(ItemDeleteHandler::class, ['item' => (int) $shop->id], []);

        static::assertEquals(
            ['/admin/extension/navigation/menus?menu=1', [['News', []]]],
            [$result->url, $this->tree()],
        );
    }

    #[Test]
    public function deletingAnItemOnlyWhenPosted(): void
    {
        $shop = $this->item($this->menu, Item::text('Shop'));

        $this->answer(ItemDeleteHandler::class, ['item' => (int) $shop->id]);

        static::assertSame([['Shop', []]], $this->tree());
    }

    #[Test]
    public function deletingAnItemThatIsGoneSaysSo(): void
    {
        static::assertSame(
            [['level' => 'warning', 'text' => 'That item no longer exists.']],
            $this->answer(ItemDeleteHandler::class, ['item' => 9], [])->messages,
        );
    }

    #[Test]
    public function editingAnItemChangesItsLinkAndDrawingAndKeepsItsPlace(): void
    {
        $this->item($this->menu, Item::text('First'));
        $shop = $this->item($this->menu, Item::text('Shop'), position: 1);

        $this->answer(
            ItemEditHandler::class,
            ['item' => (int) $shop->id],
            [
                'link'    => ['kind' => 'address', 'address' => 'https://shop.example.com'],
                'label'   => 'Shop',
                'parent'  => '',
                'display' => 'icon_label',
                'icon'    => 'cart',
                'new_tab' => '1',
                'class'   => 'nav__shop',
                'active'  => '1',
            ],
        );
        $saved = $this->menus()->repository->item((int) $shop->id);

        static::assertEquals(
            [
                ItemKind::Url,
                Url::parse('https://shop.example.com'),
                Display::IconLabel,
                'cart',
                Item::NEW_TAB,
                'nav__shop',
                true,
                1,
            ],
            [
                $saved->kind,
                $saved->url,
                $saved->display,
                $saved->icon,
                $saved->target,
                $saved->class,
                $saved->active,
                $saved->sequence,
            ],
        );
    }

    #[Test]
    public function editingAnItemOffersCreateNewUnderItsParentsPage(): void
    {
        $about  = $this->item($this->menu, Item::record(new RecordTarget('page', 1)));
        $team   = $this->item($this->menu, Item::text('Team'), $about->id);
        $result = $this->answer(ItemEditHandler::class, ['item' => (int) $team->id]);

        static::assertSame(
            ['create' => true, 'prefix' => '/about', 'label' => 'label'],
            $this->field($result, 'link')?->attributes,
        );
    }

    #[Test]
    public function editingAnItemShowsItsValuesAndLeavesItselfOutOfWhereItCanGo(): void
    {
        $shop = $this->item(
            $this->menu,
            Item::record(new RecordTarget('page', 1), 'staff')->withPresentation(
                'Team',
                Display::Icon,
                'people',
                Item::NEW_TAB,
                'x',
                false,
            ),
        );
        $this->item($this->menu, Item::text('Sale'), $shop->id);
        $this->item($this->menu, Item::text('News'), position: 1);

        $result = $this->answer(ItemEditHandler::class, ['item' => (int) $shop->id]);

        static::assertSame(
            [
                ['record:page:1#staff', 'Team', 'icon', 'people', true, 'x', false],
                ['' => 'None (top level)', '3' => '— News'],
            ],
            [
                array_map(fn(string $name): mixed => $this->field($result, $name)?->default, [
                    'link',
                    'label',
                    'display',
                    'icon',
                    'new_tab',
                    'class',
                    'active',
                ]),
                $this->field($result, 'parent')?->options,
            ],
        );
    }

    #[Test]
    public function editingAnItemThatBreaksAnAddressRuleOrNamesAParentThatIsGoneSaysWhy(): void
    {
        $shop = $this->item($this->menu, Item::text('Shop'));

        static::assertSame(
            ['A menu can\'t link to a data: address', 'There is no menu item 99'],
            [
                $this->answer(
                    ItemEditHandler::class,
                    ['item' => (int) $shop->id],
                    ['link' => ['kind' => 'address', 'address' => 'data:x'], 'label' => 'Shop'],
                )->messages[0]['text'] ?? null,
                $this->answer(
                    ItemEditHandler::class,
                    ['item' => (int) $shop->id],
                    ['label' => 'Shop', 'parent' => '99', 'active' => '1'],
                )->messages[0]['text'] ?? null,
            ],
        );
    }

    #[Test]
    public function editingAnItemThatBreaksARuleShowsWhatWasPosted(): void
    {
        $shop   = $this->item($this->menu, Item::text('Shop'));
        $result = $this->answer(
            ItemEditHandler::class,
            ['item' => (int) $shop->id],
            [
                'label'   => 'Shop',
                'display' => 'icon',
                'active'  => '1',
            ],
        );

        static::assertSame(
            ['An item drawn with an icon needs an icon token', 'icon'],
            [$result->messages[0]['text'] ?? null, $this->field($result, 'display')?->default],
        );
    }

    #[Test]
    public function editingAnItemThatIsGoneGoesBackToNavigation(): void
    {
        static::assertSame(
            [['level' => 'warning', 'text' => 'That item no longer exists.']],
            $this->answer(ItemEditHandler::class, ['item' => 9])->messages,
        );
    }

    #[Test]
    public function editingAnItemToLinkToANewPageMakesItAndLinksToIt(): void
    {
        $shop   = $this->item($this->menu, Item::text('Shop'));
        $result = $this->answer(
            ItemEditHandler::class,
            ['item' => (int) $shop->id],
            [
                'link'   => self::newPage('/shop'),
                'label'  => 'Shop',
                'active' => '1',
            ],
        );

        static::assertSame(
            ['Item saved to the draft.', [['Shop', []]], 'page:3'],
            [
                $result->messages[0]['text'] ?? null,
                $this->tree(),
                $this->linkedTo((int) $shop->id),
            ],
        );
    }

    #[Test]
    public function editingAnItemToSitUnderAnotherPutsItLastThere(): void
    {
        $shop = $this->item($this->menu, Item::text('Shop'));
        $this->item($this->menu, Item::text('Sale'), $shop->id);
        $news = $this->item($this->menu, Item::text('News'), position: 1);

        $this->answer(
            ItemEditHandler::class,
            ['item' => (int) $news->id],
            [
                'label'  => 'News',
                'parent' => (string) $shop->id,
                'active' => '1',
            ],
        );

        static::assertSame([['Shop', [['Sale', []], ['News', []]]]], $this->tree());
    }

    #[Test]
    public function editingAnItemToTextDropsItsNewTab(): void
    {
        $shop = $this->item($this->menu, Item::url(Url::parse('https://shop.example.com'), 'Shop'));

        $this->answer(
            ItemEditHandler::class,
            ['item' => (int) $shop->id],
            [
                'label'   => 'Shop',
                'new_tab' => '1',
                'active'  => '1',
            ],
        );

        static::assertNull($this->menus()->repository->item((int) $shop->id)->target);
    }

    #[Test]
    public function editingAnItemWithALinkLeftIncompleteOrAPageTheSiteRefusesSaysWhy(): void
    {
        $shop = $this->item($this->menu, Item::text('Shop'));

        static::assertSame(
            ['Choose the page or record to link to', 'Some fields need attention That path is already in use'],
            [
                $this->answer(
                    ItemEditHandler::class,
                    ['item' => (int) $shop->id],
                    [
                        'link'  => ['kind' => 'record', 'record' => ''],
                        'label' => 'Shop',
                    ],
                )->messages[0]['text'] ?? null,
                $this->answer(
                    ItemEditHandler::class,
                    ['item' => (int) $shop->id],
                    [
                        'link'  => self::newPage('/about'),
                        'label' => 'Shop',
                    ],
                )->messages[0]['text'] ?? null,
            ],
        );
    }

    #[Test]
    public function itemScreensBelongUnderTheirMenu(): void
    {
        $shop = $this->item($this->menu, Item::text('Shop'));

        static::assertSame(
            [['menu' => $this->menu], ['menu' => $this->menu]],
            [
                $this->answer(ItemAddHandler::class, ['menu' => $this->menu])->screen?->parent?->query,
                $this->answer(ItemEditHandler::class, ['item' => (int) $shop->id])->screen?->parent?->query,
            ],
        );
    }

    #[Test]
    public function movingAnItemDownAndUpAmongItsSiblings(): void
    {
        $first = $this->item($this->menu, Item::text('First'));
        $this->item($this->menu, Item::text('Second'), position: 1);

        $this->answer(ItemMoveHandler::class, ['item' => (int) $first->id], ['direction' => 'down']);
        $down = $this->tree();
        $this->answer(ItemMoveHandler::class, ['item' => (int) $first->id], ['direction' => 'up']);

        static::assertSame([[['Second', []], ['First', []]], [['First', []], ['Second', []]]], [$down, $this->tree()]);
    }

    #[Test]
    public function movingAnItemOnePlaceAtATime(): void
    {
        $first = $this->item($this->menu, Item::text('First'));
        $this->item($this->menu, Item::text('Middle'), position: 1);
        $last = $this->item($this->menu, Item::text('Last'), position: 2);

        $this->answer(ItemMoveHandler::class, ['item' => (int) $last->id], ['direction' => 'up']);
        $up = $this->tree();
        $this->answer(ItemMoveHandler::class, ['item' => (int) $first->id], ['direction' => 'down']);

        static::assertSame(
            [[['First', []], ['Last', []], ['Middle', []]], [['Last', []], ['First', []], ['Middle', []]]],
            [$up, $this->tree()],
        );
    }

    #[Test]
    public function movingAnItemUnderAParentAtAPosition(): void
    {
        $shop = $this->item($this->menu, Item::text('Shop'));
        $this->item($this->menu, Item::text('Sale'), $shop->id);
        $news = $this->item($this->menu, Item::text('News'), position: 1);

        $result = $this->answer(
            ItemMoveHandler::class,
            ['item' => (int) $news->id],
            ['parent' => (string) $shop->id, 'position' => '0'],
        );

        static::assertSame(
            ['/admin/extension/navigation/menus?menu=1', [['Shop', [['News', []], ['Sale', []]]]]],
            [$result->url, $this->tree()],
        );
    }

    #[Test]
    public function movingAnItemUnderItselfIsRefused(): void
    {
        $shop = $this->item($this->menu, Item::text('Shop'));

        $result = $this->answer(ItemMoveHandler::class, ['item' => (int) $shop->id], ['parent' => (string) $shop->id]);

        static::assertSame(
            [['level' => 'danger', 'text' => 'An item can\'t be moved under itself']],
            $result->messages,
        );
    }

    #[Test]
    public function movingOnlyWhenPostedAndAnItemThatIsGoneSaysSo(): void
    {
        $shop = $this->item($this->menu, Item::text('Shop'));

        static::assertSame(
            [
                '/admin/extension/navigation/menus',
                [],
                [['level' => 'warning', 'text' => 'That item no longer exists.']],
            ],
            [
                $this->answer(ItemMoveHandler::class, ['item' => (int) $shop->id])->url,
                $this->answer(ItemMoveHandler::class, ['item' => (int) $shop->id])->messages,
                $this->answer(ItemMoveHandler::class, ['item' => 9], ['direction' => 'up'])->messages,
            ],
        );
    }

    #[Test]
    public function theSwitchChangesNothingUnlessPosted(): void
    {
        $shop   = $this->item($this->menu, Item::text('Shop'));
        $result = $this->answer(ItemVisibilityHandler::class, ['item' => (int) $shop->id]);

        static::assertSame(
            ['/admin/extension/navigation/menus', [], true],
            [$result->url, $result->messages, $this->menus()->repository->item((int) $shop->id)->active],
        );
    }

    /**
     * @param array<string, string> $posted
     */
    #[Test]
    #[DataProvider('visibilityProvider')]
    public function theSwitchOnAnItemsRowShowsOrHidesIt(array $posted, bool $shown): void
    {
        $shop = $this->item(
            $this->menu,
            Item::text('Shop')->withPresentation('Shop', Display::Label, null, null, null, ! $shown),
        );

        $result = $this->answer(ItemVisibilityHandler::class, ['item' => (int) $shop->id], $posted);

        static::assertSame(
            ['/admin/extension/navigation/menus?menu=1', $shown],
            [$result->url, $this->menus()->repository->item((int) $shop->id)->active],
        );
    }

    #[Test]
    public function theSwitchOnAnItemThatIsGoneSaysSo(): void
    {
        static::assertSame(
            [['level' => 'warning', 'text' => 'That item no longer exists.']],
            $this->answer(ItemVisibilityHandler::class, ['item' => 9], [])->messages,
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
        $this->menu = $this->menu();
    }

    private function field(HandlerResult $result, string $name): ?Field
    {
        return $result->screen?->form?->field($name);
    }

    /**
     * The record the item with $id links to, as "<section>:<id>".
     */
    private function linkedTo(int $id): string
    {
        $record = $this->menus()->repository->item($id)->record;

        return "{$record?->section}:{$record?->id}";
    }

    /**
     * The menu's draft as each item's label, or a record item's section and
     * id with its fragment, and its children.
     *
     * @return list<array{string, list<mixed>}>
     */
    private function tree(): array
    {
        $shape = static function (array $nodes) use (&$shape): array {
            return array_map(static fn(Node $node): array => [
                $node->item->label ?? trim(
                    "{$node->item->record?->section} {$node->item->record?->id} "
                        . (null === $node->item->fragment ? '' : "#{$node->item->fragment}"),
                ),
                $shape($node->children),
            ], $nodes);
        };

        return $shape(Tree::build($this->menus()->repository->items($this->menu)));
    }
}
