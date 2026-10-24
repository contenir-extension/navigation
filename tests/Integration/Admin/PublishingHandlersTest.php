<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Integration\Admin;

use Contenir\Extension\Api\Editor\Editor;
use Contenir\Extension\Navigation\Admin\DiscardHandler;
use Contenir\Extension\Navigation\Admin\HistoryHandler;
use Contenir\Extension\Navigation\Admin\PublishHandler;
use Contenir\Extension\Navigation\Admin\RestoreHandler;
use Contenir\Extension\Navigation\NavigationExtension;
use Contenir\Extension\Navigation\Tests\Trait\NavigationHostTrait;
use Contenir\Navigation\Menu\Item;
use Override;
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

    #[Test]
    public function discardingAMenuNeverPublishedSaysThereIsNothingToGoBackTo(): void
    {
        $result = $this->answer(DiscardHandler::class, ['menu' => $this->menu], []);

        static::assertSame(
            [
                [[
                    'level' => 'warning',
                    'text'  => 'The menu has never been published, so there is nothing to go back to.',
                ]],
                1,
            ],
            [$result->messages, count($this->menus()->repository->items($this->menu))],
        );
    }

    #[Test]
    public function discardingOnlyWhenPostedAndAMenuThatIsGoneSaysSo(): void
    {
        static::assertSame(
            [
                '/admin/extension/navigation/menus?menu=1',
                [['level' => 'warning', 'text' => 'That menu no longer exists.']],
            ],
            [
                $this->answer(DiscardHandler::class, ['menu' => $this->menu])->url,
                $this->answer(DiscardHandler::class, ['menu' => 9], [])->messages,
            ],
        );
    }

    #[Test]
    public function discardingReturnsTheDraftToWhatIsLive(): void
    {
        $this->answer(PublishHandler::class, ['menu' => $this->menu], []);
        $this->item($this->menu, Item::text('Sale'), position: 1);

        $result = $this->answer(DiscardHandler::class, ['menu' => $this->menu], []);

        static::assertSame(
            [[['level' => 'success', 'text' => 'Changes discarded.']], 1],
            [$result->messages, count($this->menus()->repository->items($this->menu))],
        );
    }

    #[Test]
    public function historyDrawsEachRevisionWithAWayToRestoreTheOthers(): void
    {
        $this->answer(PublishHandler::class, ['menu' => $this->menu], ['note' => 'First & best']);
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
            ['note' => 'First'],
            new Editor(4, 'Ann Editor'),
        );
        $this->answer(PublishHandler::class, ['menu' => $this->menu], ['note' => '']);

        static::assertSame(
            [
                ['id' => 2, 'published' => '23 Oct 2026, 9:00 am', 'by' => null, 'note' => null, 'live' => true],
                [
                    'id'        => 1,
                    'published' => '23 Oct 2026, 9:00 am',
                    'by'        => 'Ann Editor',
                    'note'      => 'First',
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
    public function publishingANoteTooLongSaysWhy(): void
    {
        $result = $this->answer(
            PublishHandler::class,
            ['menu' => $this->menu],
            ['note' => str_repeat('a', times: 256)],
        );

        static::assertSame('danger', $result->messages[0]['level'] ?? null);
    }

    #[Test]
    public function publishingMakesTheDraftLiveWithItsNote(): void
    {
        $result = $this->answer(PublishHandler::class, ['menu' => $this->menu], ['note' => 'Added Shop']);

        static::assertSame(
            [[['level' => 'success', 'text' => 'Menu published.']], 'Added Shop', 1],
            [
                $result->messages,
                $this->menus()->repository->revision(1)->note,
                $this->menus()->repository->navigation($this->menu)->revisionId,
            ],
        );
    }

    #[Test]
    public function publishingNeedsApproveSoAnEditorCanDraftWithoutMakingItLive(): void
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
    public function restoringCopiesARevisionIntoTheDraft(): void
    {
        $this->answer(PublishHandler::class, ['menu' => $this->menu], []);
        $this->item($this->menu, Item::text('Sale'), position: 1);
        $this->answer(PublishHandler::class, ['menu' => $this->menu], []);

        $result = $this->answer(RestoreHandler::class, ['revision' => 1], []);

        static::assertSame(
            ['/admin/extension/navigation/menus?menu=1', 1],
            [$result->url, count($this->menus()->repository->items($this->menu))],
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
