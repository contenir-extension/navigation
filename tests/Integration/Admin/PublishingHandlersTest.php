<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Integration\Admin;

use Contenir\Extension\Api\Editor\Editor;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Navigation\Admin\HistoryHandler;
use Contenir\Extension\Navigation\Admin\ItemAddHandler;
use Contenir\Extension\Navigation\Admin\ItemDeleteHandler;
use Contenir\Extension\Navigation\Admin\ItemEditHandler;
use Contenir\Extension\Navigation\Admin\ItemMoveHandler;
use Contenir\Extension\Navigation\Admin\ItemVisibilityHandler;
use Contenir\Extension\Navigation\Admin\PublishHandler;
use Contenir\Extension\Navigation\Admin\RestoreHandler;
use Contenir\Extension\Navigation\NavigationExtension;
use Contenir\Extension\Navigation\Tests\Trait\NavigationHostTrait;
use Contenir\Navigation\Menu\Item;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;
use function str_contains;
use function str_repeat;

#[Group('integration')]
#[Group('handler')]
final class PublishingHandlersTest extends TestCase
{
    use NavigationHostTrait;

    private int $menu;

    /**
     * @return array<string, array{class-string<HandlerInterface>, string, array<string, string>, string|null}>
     */
    public static function liveChangeProvider(): array
    {
        return [
            'adding an item'   => [
                ItemAddHandler::class,
                'menu',
                ['link' => '{"kind":"text"}', 'label' => 'Sale', 'active' => '1'],
                'Item added.',
            ],
            'editing an item'  => [
                ItemEditHandler::class,
                'item',
                ['link' => '{"kind":"text"}', 'label' => 'Shop now', 'active' => '1'],
                'Item saved.',
            ],
            'deleting an item' => [ItemDeleteHandler::class, 'item', [], 'Item deleted.'],
            'moving an item'   => [ItemMoveHandler::class, 'item', ['direction' => 'down'], null],
            'hiding an item'   => [ItemVisibilityHandler::class, 'item', [], null],
        ];
    }

    /**
     * @return array<string, array{string, array<string, int>, int, int|null}>
     */
    public static function unrecordableEditorProvider(): array
    {
        return [
            'publishing'          => ['publish', ['menu' => 1], 0, null],
            'deleting an item'    => ['item-delete', ['item' => 1], 1, 1],
            'restoring a version' => ['restore', ['revision' => 1], 2, 2],
            'hiding an item'      => ['item-visibility', ['item' => 1], 1, 1],
        ];
    }

    /**
     * @param class-string<HandlerInterface> $handler
     * @param array<string, string>          $posted
     */
    #[Test]
    #[DataProvider('liveChangeProvider')]
    public function aChangeToALiveMenuGoesLiveAsItIsSaved(
        string $handler,
        string $by,
        array $posted,
        ?string $said,
    ): void {
        $this->item($this->menu, Item::text('Sale'), position: 1);
        $this->answer(PublishHandler::class, ['menu' => $this->menu], []);

        $result = $this->answer($handler, [$by => 'menu' === $by ? $this->menu : 1], $posted);
        $menu   = $this->menus()->repository->navigation($this->menu);

        static::assertSame(
            [$said, 2, 2],
            [
                $result->messages[0]['text'] ?? null,
                count($this->menus()->repository->revisions($this->menu)),
                $menu->revisionId,
            ],
        );
    }

    #[Test]
    public function aChangeToAMenuNotYetPublishedStaysInItsDraft(): void
    {
        $this->answer(ItemDeleteHandler::class, ['item' => 1], []);

        static::assertSame(
            [null, []],
            [
                $this->menus()->repository->navigation($this->menu)->revisionId,
                $this->menus()->repository->revisions($this->menu),
            ],
        );
    }

    /**
     * Publishes the menu $published times as someone who can be recorded, then asks $handler as an
     * editor whose id is too long to record, and expects to be sent back with the live revision
     * still $live.
     *
     * @param array<string, int> $query
     */
    #[Test]
    #[DataProvider('unrecordableEditorProvider')]
    public function aChangeWhoseEditorCannotBeRecordedSaysSoAndLeavesTheSiteAsItWas(
        string $handler,
        array $query,
        int $published,
        ?int $live,
    ): void {
        for ($time = 0; $time < $published; $time++) {
            $this->answer(PublishHandler::class, ['menu' => $this->menu], []);
        }

        $response = $this->request(
            $handler,
            $query,
            [NavigationExtension::SECTION],
            [],
            new Editor(str_repeat('a', times: 65), 'Ann Editor'),
        );

        static::assertSame(
            [302, $live],
            [$response->getStatusCode(), $this->menus()->repository->navigation($this->menu)->revisionId],
        );
    }

    #[Test]
    public function historyDrawsEachRevisionWithAnyNoteAndAWayToRestoreTheOthers(): void
    {
        $this->menus()->publisher->publish($this->menu, note: 'First & best');
        $this->answer(PublishHandler::class, ['menu' => $this->menu], []);

        $body = (string) $this->request('history', ['menu' => $this->menu])->getBody();

        static::assertSame(
            [true, true, false],
            [
                str_contains($body, 'First &amp; best'),
                str_contains($body, '/admin/extension/navigation/restore?revision=1'),
                str_contains($body, '/admin/extension/navigation/restore?revision=2'),
            ],
        );
    }

    #[Test]
    public function historyListsRevisionsNewestFirstWithTheLiveOneMarked(): void
    {
        $this->request(
            'publish',
            ['menu' => $this->menu],
            [NavigationExtension::SECTION],
            [],
            new Editor(4, 'Ann Editor'),
        );
        $this->answer(PublishHandler::class, ['menu' => $this->menu], []);

        static::assertSame(
            [
                ['id' => 2, 'published' => '23 Oct 2026, 9:00am', 'by' => null, 'note' => null, 'live' => true],
                [
                    'id'        => 1,
                    'published' => '23 Oct 2026, 9:00am',
                    'by'        => 'Ann Editor',
                    'note'      => null,
                    'live'      => false,
                ],
            ],
            $this->answer(HistoryHandler::class, ['menu' => $this->menu])->template?->values['revisions'] ?? null,
        );
    }

    #[Test]
    public function historyNamesItsMenuAndBelongsUnderIt(): void
    {
        $result = $this->answer(HistoryHandler::class, ['menu' => $this->menu]);

        static::assertSame(
            [['id' => $this->menu, 'title' => 'Header'], ['menu' => $this->menu], 'Header history'],
            [$result->template?->values['menu'] ?? null, $result->template?->parent?->query, $result->template?->title],
        );
    }

    #[Test]
    public function historyOfAMenuThatIsGoneGoesBackToNavigation(): void
    {
        static::assertSame(
            '/admin/extension/navigation/menus',
            $this->answer(HistoryHandler::class, ['menu' => 9])->url,
        );
    }

    #[Test]
    public function publishingAMenuTheFirstTimeNeedsApprove(): void
    {
        static::assertSame(
            [403, 302],
            [
                $this->request(
                    'publish',
                    ['menu' => $this->menu],
                    [NavigationExtension::SECTION . ':update'],
                    [],
                )->getStatusCode(),
                $this->request(
                    'publish',
                    ['menu' => $this->menu],
                    [NavigationExtension::SECTION . ':approve'],
                    [],
                )->getStatusCode(),
            ],
        );
    }

    #[Test]
    public function publishingOnlyWhenPostedAndAMenuThatIsGoneSaysSo(): void
    {
        static::assertSame(
            [null, [['level' => 'warning', 'text' => 'That menu no longer exists.']]],
            [
                $this->answer(PublishHandler::class, ['menu' => $this->menu])->messages[0] ?? null,
                $this->answer(PublishHandler::class, ['menu' => 9], [])->messages,
            ],
        );
    }

    #[Test]
    public function publishingPutsTheMenuOnTheSite(): void
    {
        $result = $this->answer(PublishHandler::class, ['menu' => $this->menu], []);

        static::assertSame(
            [[['level' => 'success', 'text' => 'Menu published.']], null, 1],
            [
                $result->messages,
                $this->menus()->repository->revision(1)->note,
                $this->menus()->repository->navigation($this->menu)->revisionId,
            ],
        );
    }

    #[Test]
    public function restoringARevisionOfALiveMenuPutsItBackOnTheSite(): void
    {
        $this->answer(PublishHandler::class, ['menu' => $this->menu], []);
        $this->item($this->menu, Item::text('Sale'), position: 1);
        $this->answer(PublishHandler::class, ['menu' => $this->menu], []);

        $result = $this->answer(RestoreHandler::class, ['revision' => 1], []);

        static::assertSame(
            ['/admin/extension/navigation/menus?menu=1', 'Revision restored and live.', 1, 3],
            [
                $result->url,
                $result->messages[0]['text'] ?? null,
                count($this->menus()->repository->items($this->menu)),
                $this->menus()->repository->navigation($this->menu)->revisionId,
            ],
        );
    }

    #[Test]
    public function restoringOnlyWhenPostedAndARevisionThatIsGoneSaysSo(): void
    {
        static::assertSame(
            [
                '/admin/extension/navigation/menus',
                [['level' => 'warning', 'text' => 'That revision no longer exists.']],
            ],
            [
                $this->answer(RestoreHandler::class, ['revision' => 1])->url,
                $this->answer(RestoreHandler::class, ['revision' => 9], [])->messages,
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
        $this->menu = $this->menu();
        $this->item($this->menu, Item::text('Shop'));
    }
}
