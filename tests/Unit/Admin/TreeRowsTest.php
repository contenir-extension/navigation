<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Admin;

use Contenir\Extension\Navigation\Admin\TreeRows;
use Contenir\Navigation\Link\RecordTarget;
use Contenir\Navigation\Link\Url;
use Contenir\Navigation\Menu\Display;
use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\Node;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TreeRowsTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function addressProvider(): array
    {
        return [
            'a path'      => ['/shop', 'link'],
            'a full link' => ['https://example.com', 'external-link'],
            'a fragment'  => ['#booking', 'hash'],
            'an email'    => ['hello@example.com', 'mail'],
            'a phone'     => ['03 5555 1234', 'phone'],
        ];
    }

    #[Test]
    #[DataProvider('addressProvider')]
    public function anAddressHasTheIconOfItsKind(string $address, string $icon): void
    {
        $rows = (new TreeRows(null))->rows([new Node(Item::url(Url::parse($address), 'Go'))]);

        static::assertSame($icon, $rows[0]['icon']);
    }

    #[Test]
    public function aRecordItemWhereTheHostLendsNoRecordsIsNamedBySectionAndIdWithNoBadge(): void
    {
        $rows = (new TreeRows(null))->rows([new Node(Item::record(new RecordTarget('page', 4)))]);

        static::assertSame(
            [
                'id'       => 0,
                'label'    => 'page 4',
                'icon'     => 'file',
                'path'     => null,
                'badge'    => null,
                'drawn'    => 'Label',
                'children' => [],
            ],
            $rows[0],
        );
    }

    #[Test]
    public function saysHowAnItemIsDrawn(): void
    {
        $rows = (new TreeRows(null))->rows([
            new Node(Item::text('A')->withPresentation('A', Display::Icon, 'star')),
            new Node(Item::text('B')->withPresentation('B', Display::IconLabel, 'star')),
        ]);

        static::assertSame(['Icon "star"', 'Icon "star" with label'], [$rows[0]['drawn'], $rows[1]['drawn']]);
    }
}
