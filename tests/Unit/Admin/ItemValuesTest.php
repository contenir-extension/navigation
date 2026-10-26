<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Admin;

use Contenir\Extension\Navigation\Admin\Input;
use Contenir\Extension\Navigation\Admin\ItemValues;
use Contenir\Navigation\Link\RecordTarget;
use Contenir\Navigation\Link\Url;
use Contenir\Navigation\Menu\Display;
use Contenir\Navigation\Menu\Item;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ItemValuesTest extends TestCase
{
    #[Test]
    public function anAddressShowsAsTypedAndTextShowsItsHtmlAtTheTop(): void
    {
        $email = ItemValues::of(Item::url(Url::parse('hello@example.com'), 'Email us'));
        $text  = ItemValues::of(Item::text('Hours', '<b>Open</b>'));

        static::assertSame(
            [
                ['address:hello@example.com', '', false],
                [null, '<b>Open</b>', '', true],
            ],
            [
                [$email['link'], $email['parent'], $email['new_tab']],
                [$text['link'], $text['html'], $text['parent'], $text['active']],
            ],
        );
    }

    #[Test]
    public function aRecordItemShowsItsRecordFragmentAndDrawing(): void
    {
        $item = Item::record(new RecordTarget('page', 4), 'staff', 'Team')
            ->withPresentation('Team', Display::Icon, 'people', Item::NEW_TAB, 'x', false)
            ->withPlacement(1, 3, 0);

        static::assertSame(
            [
                'link'    => 'record:page:4#staff',
                'label'   => 'Team',
                'html'    => null,
                'parent'  => '3',
                'display' => 'icon',
                'icon'    => 'people',
                'new_tab' => true,
                'class'   => 'x',
                'active'  => false,
            ],
            ItemValues::of($item),
        );
    }

    #[Test]
    public function showsEverythingThatWasPosted(): void
    {
        $posted = [
            'label'   => 'Shop',
            'html'    => '<b>x</b>',
            'parent'  => '2',
            'display' => 'icon_label',
            'icon'    => 'cart',
            'class'   => 'y',
        ];

        static::assertSame(
            [...$posted, 'link' => 'address:/shop', 'new_tab' => true, 'active' => false],
            ItemValues::posted(Input::of(
                (new Psr17Factory())->createServerRequest('POST', '/')
                    ->withParsedBody([
                        ...$posted,
                        'link'    => ['kind' => 'address', 'address' => '/shop'],
                        'new_tab' => '1',
                    ]),
            )),
        );
    }

    #[Test]
    public function showsNoLinkWhenWhatWasPostedIsIncompleteOrANewPage(): void
    {
        $posted = static fn(array $link): mixed => ItemValues::posted(Input::of(
            (new Psr17Factory())->createServerRequest('POST', '/')
                ->withParsedBody(['link' => $link]),
        ))['link'];

        static::assertSame(
            [null, null],
            [
                $posted(['kind' => 'record', 'record' => '']),
                $posted(['kind' => 'new', 'section' => 'page', 'title' => 'Story', 'path' => '/story']),
            ],
        );
    }
}
