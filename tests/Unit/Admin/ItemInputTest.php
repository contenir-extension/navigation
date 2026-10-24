<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Admin;

use Contenir\Extension\Navigation\Admin\Input;
use Contenir\Extension\Navigation\Admin\ItemInput;
use Contenir\Navigation\Exception\InvalidItemException;
use Contenir\Navigation\Link\RecordTarget;
use Contenir\Navigation\Link\Url;
use Contenir\Navigation\Menu\Display;
use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\ItemKind;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ItemInputTest extends TestCase
{
    /**
     * @param array<string, string> $posted
     */
    private static function input(array $posted): ItemInput
    {
        return new ItemInput(Input::of(
            (new Psr17Factory())->createServerRequest('POST', '/')
                ->withParsedBody($posted),
        ));
    }

    #[Test]
    public function aChangedItemKeepsItsDrawingPlaceAndWhenItWasMade(): void
    {
        $created = new DateTimeImmutable('2026-10-20 09:00:00');
        $item    = new Item(
            ItemKind::Text,
            label: 'Shop',
            display: Display::Icon,
            icon: 'cart',
            class: 'x',
            active: false,
            id: 5,
            navigationId: 2,
            parentId: 3,
            sequence: 4,
            created: $created,
        );

        static::assertEquals(
            new Item(
                ItemKind::Url,
                url: Url::parse('/shop'),
                label: 'Store',
                display: Display::Icon,
                icon: 'cart',
                class: 'x',
                active: false,
                id: 5,
                navigationId: 2,
                parentId: 3,
                sequence: 4,
                created: $created,
            ),
            self::input(['links_to' => 'url', 'url' => '/shop', 'label' => 'Store'])->item($item),
        );
    }

    #[Test]
    public function aChangedItemKeepsItsNewTabUnlessItBecomesText(): void
    {
        $shop = Item::url(Url::parse('https://shop.example.com'), 'Shop');

        static::assertSame(
            [Item::NEW_TAB, null],
            [
                self::input(['links_to' => 'url', 'url' => 'https://shop.example.com', 'label' => 'Shop'])
                    ->item($shop)
                    ->target,
                self::input(['links_to' => 'text', 'label' => 'Shop'])->item($shop)->target,
            ],
        );
    }

    #[Test]
    public function anythingElseIsAnAddress(): void
    {
        static::assertEquals(
            Item::url(Url::parse('#booking'), 'Book'),
            self::input(['links_to' => 'other', 'url' => '#booking', 'label' => 'Book'])->item(),
        );
    }

    #[Test]
    public function aRecordItemNamesItsSectionIdFragmentAndLabel(): void
    {
        static::assertEquals(
            Item::record(new RecordTarget('page', 'about:us'), 'staff', 'Team'),
            self::input([
                'links_to' => 'record',
                'record'   => 'page:about:us',
                'fragment' => '#staff',
                'label'    => 'Team',
            ])->item(),
        );
    }

    #[Test]
    public function aRecordItemNeedsARecordChosen(): void
    {
        $this->expectException(InvalidItemException::class);
        $this->expectExceptionMessage('Choose the page or record the item links to');

        self::input(['links_to' => 'record', 'record' => 'page'])->item();
    }

    #[Test]
    public function readsTheParentChosen(): void
    {
        static::assertSame([3, null], [
            self::input(['parent' => '3'])->parent(),
            self::input(['parent' => ''])->parent(),
        ]);
    }

    #[Test]
    public function textKeepsItsHtml(): void
    {
        static::assertEquals(
            Item::text('Hours', '<b>Open</b>'),
            self::input(['links_to' => 'text', 'label' => 'Hours', 'html' => '<b>Open</b>'])->item(),
        );
    }

    #[Test]
    public function theDrawingIsAsTheFormSays(): void
    {
        $item = Item::url(Url::parse('/shop'), 'Shop');

        static::assertEquals(
            $item->withPresentation('Shop', Display::IconLabel, 'cart', Item::NEW_TAB, 'nav__shop', true),
            self::input([
                'display' => 'icon_label',
                'icon'    => 'cart',
                'new_tab' => '1',
                'class'   => 'nav__shop',
                'active'  => '1',
            ])->presented($item),
        );
    }

    #[Test]
    public function theDrawingOfTextNeverOpensANewTabAndAnUnknownDisplayIsTheLabel(): void
    {
        $item = Item::text('Shop');

        static::assertEquals(
            $item->withPresentation('Shop', active: false),
            self::input(['display' => 'sideways', 'new_tab' => '1'])->presented($item),
        );
    }
}
