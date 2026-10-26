<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Admin;

use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Record\RecordsInterface;
use Contenir\Extension\Api\Record\RecordStatus;
use Contenir\Extension\Api\Record\RecordSummary;
use Contenir\Extension\Api\Url\RecordReference;
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
    public function anAddressOrTextNamesNothingToEdit(): void
    {
        $rows = (new TreeRows(null))->rows([
            new Node(Item::url(Url::parse('/shop'), 'Shop')),
            new Node(Item::text('Hello')),
        ]);

        static::assertSame([null, null], [$rows[0]['linked'], $rows[1]['linked']]);
    }

    #[Test]
    public function aRecordInASectionTheHostDoesNotListIsARecord(): void
    {
        $summary = new RecordSummary(
            new RecordReference('page', 4),
            'About',
            RecordStatus::Published,
            '/about',
            '/admin/edit/4',
        );
        $records = $this->createStub(RecordsInterface::class);
        $records->method('sections')->willReturn([]);
        $records->method('describe')->willReturn($summary);

        $rows = (new TreeRows($records))->rows([new Node(Item::record(new RecordTarget('page', 4)))]);

        static::assertSame('Record', $rows[0]['linked']['type'] ?? null);
    }

    #[Test]
    public function aRecordItemNamesItsRecordsEditAddressAndWhatItsSectionCallsIt(): void
    {
        $rows = $this->rowsOf(new RecordSummary(
            new RecordReference('page', 4),
            'About',
            RecordStatus::Published,
            '/about',
            '/admin/site/common/update/resource_id/4',
        ));

        static::assertSame(['url' => '/admin/site/common/update/resource_id/4', 'type' => 'Page'], $rows[0]['linked']);
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
                'active'   => true,
                'drawn'    => 'Label',
                'linked'   => null,
                'children' => [],
            ],
            $rows[0],
        );
    }

    #[Test]
    public function aRecordItemWhoseRecordHasNoEditAddressNamesNoneToEdit(): void
    {
        $rows = $this->rowsOf(
            new RecordSummary(new RecordReference('page', 4), 'About', RecordStatus::Published, '/about'),
        );

        static::assertNull($rows[0]['linked']);
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

    /**
     * The rows of one item linked to page 4, which the host describes as $summary, in a site whose
     * page section calls its records Page.
     *
     * @return list<array<string, mixed>>
     */
    private function rowsOf(RecordSummary $summary): array
    {
        $records = $this->createStub(RecordsInterface::class);
        $records->method('sections')->willReturn([new LinkableSection('page', 'Page')]);
        $records->method('describe')->willReturn($summary);

        return (new TreeRows($records))->rows([new Node(Item::record(new RecordTarget('page', 4)))]);
    }
}
