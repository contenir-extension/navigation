<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Integration\Admin;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Navigation\Admin\MenuAddHandler;
use Contenir\Extension\Navigation\Admin\MenuDeleteHandler;
use Contenir\Extension\Navigation\Admin\MenuEditHandler;
use Contenir\Extension\Navigation\Admin\PublishHandler;
use Contenir\Extension\Navigation\NavigationExtension;
use Contenir\Extension\Navigation\Tests\Trait\NavigationHostTrait;
use Contenir\Navigation\Menu\Item;
use Contenir\Navigation\Menu\Navigation;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function count;
use function str_contains;

#[Group('integration')]
#[Group('handler')]
final class MenuHandlersTest extends TestCase
{
    use NavigationHostTrait;

    /**
     * @return array<string, array{list<array{string, string, bool}>, string|null}>
     */
    public static function sharedProvider(): array
    {
        return [
            'no other menu has it'            => [[['footer', 'Footer', false]], null],
            'an unpublished one has it'       => [
                [['header', 'Header', false]],
                'Also the identifier of menu "Header". None of them is published yet.',
            ],
            'an earlier published one has it' => [
                [['header', 'Header', true]],
                'Also the identifier of menu "Header". The site shows "Header", the first published in menu order.',
            ],
            'this is the one the site shows'  => [
                [['header', 'Header', false]],
                'Also the identifier of menu "Header". The site shows this one, the first published in menu order.',
            ],
            'two others have it'              => [
                [
                    ['header', 'Header',       false],
                    ['header', 'Header (old)', false],
                ],
                'Also the identifier of menus "Header", "Header (old)". None of them is published yet.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function fieldNames(HandlerResult $result): array
    {
        return array_map(static fn(Field $field): string => $field->name, $result->screen?->form->fields ?? []);
    }

    #[Test]
    public function addingAMenuOffersItsForm(): void
    {
        $result = $this->answer(MenuAddHandler::class);

        static::assertSame(
            [ResultKind::Screen, ['title', 'handle', 'description'], 'Navigation', [], []],
            [
                $result->kind,
                self::fieldNames($result),
                $result->screen?->parent?->title,
                $result->messages,
                $this->menus()->repository->navigations(),
            ],
        );
    }

    #[Test]
    public function addingAMenuPutsItAfterTheOthersAndShowsIt(): void
    {
        $this->menu('header');

        $result = $this->answer(MenuAddHandler::class, posted: [
            'title'       => 'Footer',
            'handle'      => 'footer',
            'description' => 'At the foot',
        ]);

        static::assertEquals(
            [
                '/admin/extension/navigation/menus?menu=2',
                [['level' => 'success', 'text' => 'Menu added.']],
                new Navigation(
                    'footer',
                    'Footer',
                    'At the foot',
                    1,
                    2,
                    null,
                    $this->menus()->repository->navigation(2)->updated,
                ),
            ],
            [$result->url, $result->messages, $this->menus()->repository->navigation(2)],
        );
    }

    #[Test]
    public function addingAMenuWithAHandleAnotherHasSaysWhichTheSiteShows(): void
    {
        $header = $this->menu('header');
        $this->answer(PublishHandler::class, ['menu' => $header], []);

        $result = $this->answer(MenuAddHandler::class, posted: ['title' => 'Header (copy)', 'handle' => 'header']);

        static::assertSame(
            [[
                'level' => 'warning',
                'text' => 'Menu added. Also the identifier of menu "Header". The site shows "Header", the first published in menu order.',
            ]],
            $result->messages,
        );
    }

    #[Test]
    public function addingMenusNeedsAddAndDeletingThemDelete(): void
    {
        $menu = $this->menu();

        static::assertSame(
            [403, 200, 403, 302],
            [
                $this->request('menu-add', grants: [NavigationExtension::SECTION . ':update'])->getStatusCode(),
                $this->request('menu-add', grants: [NavigationExtension::SECTION . ':add'])->getStatusCode(),
                $this->request(
                    'menu-delete',
                    ['menu' => $menu],
                    [NavigationExtension::SECTION . ':update'],
                    [],
                )->getStatusCode(),
                $this->request(
                    'menu-delete',
                    ['menu' => $menu],
                    [NavigationExtension::SECTION . ':delete'],
                    [],
                )->getStatusCode(),
            ],
        );
    }

    #[Test]
    public function aHandleAnotherMenuHasIsSavedWithAWarning(): void
    {
        $this->menu('header');
        $footer = $this->menu('footer', 'Footer');

        $result = $this->answer(
            MenuEditHandler::class,
            ['menu' => $footer],
            ['title' => 'Footer', 'handle' => 'header'],
        );

        static::assertSame(
            [
                [[
                    'level' => 'warning',
                    'text'  => 'Menu saved. Also the identifier of menu "Header". None of them is published yet.',
                ]],
                'header',
            ],
            [$result->messages, $this->menus()->repository->navigation($footer)->handle],
        );
    }

    #[Test]
    public function aMenuThatBreaksARuleIsShownAgainWithWhy(): void
    {
        $result = $this->answer(MenuAddHandler::class, posted: ['title' => 'Main', 'handle' => 'Main Menu']);

        static::assertSame(
            [ResultKind::Screen, 'Main Menu', 'danger'],
            [$result->kind, $result->screen?->form?->field('handle')?->default, $result->messages[0]['level'] ?? null],
        );
    }

    #[Test]
    public function aRefusedMenuIsShownAgainWarningAboutItsHandle(): void
    {
        $this->menu('header');

        $result = $this->answer(MenuAddHandler::class, posted: ['title' => '', 'handle' => 'header']);

        static::assertSame(
            'Also the identifier of menu "Header". None of them is published yet.',
            $result->screen?->form?->field('handle')?->attributes['warning'] ?? null,
        );
    }

    #[Test]
    public function deletingAMenuDeletesItsItemsAndShowsTheRest(): void
    {
        $menu = $this->menu();
        $this->item($menu, Item::text('Shop'));
        $this->menu('footer', 'Footer');

        $result = $this->answer(MenuDeleteHandler::class, ['menu' => $menu], []);

        static::assertSame(
            ['/admin/extension/navigation/menus', ['footer'], []],
            [
                $result->url,
                array_map(
                    static fn(Navigation $left): string => $left->handle,
                    $this->menus()->repository->navigations(),
                ),
                $this->menus()->repository->items($menu),
            ],
        );
    }

    #[Test]
    public function deletingAMenuOnlyWhenPosted(): void
    {
        $menu = $this->menu();

        $result = $this->answer(MenuDeleteHandler::class, ['menu' => $menu]);

        static::assertSame(
            ['/admin/extension/navigation/menus?menu=1', 1],
            [$result->url, count($this->menus()->repository->navigations())],
        );
    }

    #[Test]
    public function editingAMenuKeepsItsPlaceAndLiveRevision(): void
    {
        $this->menu('top');
        $menu = $this->menu('header', 'Header');
        $this->answer(PublishHandler::class, ['menu' => $menu], []);

        $result = $this->answer(
            MenuEditHandler::class,
            ['menu' => $menu],
            ['title' => 'Main', 'handle' => 'main', 'description' => ''],
        );
        $saved = $this->menus()->repository->navigation($menu);

        static::assertSame(
            ['/admin/extension/navigation/menus?menu=2', 'main', 'Main', null, 1, 1],
            [$result->url, $saved->handle, $saved->title, $saved->description, $saved->sequence, $saved->revisionId],
        );
    }

    #[Test]
    public function editingAMenuShowsItsSettings(): void
    {
        $menu = (int) $this->menus()->repository->saveNavigation(new Navigation('footer', 'Footer', 'At the foot'))->id;

        $result = $this->answer(MenuEditHandler::class, ['menu' => $menu]);

        static::assertSame(
            ['Edit Footer', 'Footer', 'footer', 'At the foot', ['menu' => $menu]],
            [
                $result->screen?->title,
                $result->screen?->form?->field('title')?->default,
                $result->screen?->form?->field('handle')?->default,
                $result->screen?->form?->field('description')?->default,
                $result->screen?->parent?->query,
            ],
        );
    }

    #[Test]
    public function editingAMenuThatIsGoneGoesBackToNavigation(): void
    {
        $result = $this->answer(MenuEditHandler::class, ['menu' => 9]);

        static::assertSame(
            ['/admin/extension/navigation/menus', [['level' => 'warning', 'text' => 'That menu no longer exists.']]],
            [$result->url, $result->messages],
        );
    }

    /**
     * Edits a header menu added after $others, published when the case says this is the one the site
     * shows, and expects the handle field to warn $warning.
     *
     * @param list<array{string, string, bool}> $others Each other menu's handle, title and whether it is published
     */
    #[Test]
    #[DataProvider('sharedProvider')]
    public function editingAMenuWarnsBesideAHandleOthersShare(array $others, ?string $warning): void
    {
        foreach ($others as [$handle, $title, $published]) {
            $other = $this->menu($handle, $title);
            if ($published) {
                $this->answer(PublishHandler::class, ['menu' => $other], []);
            }
        }
        $menu = $this->menu('header', 'Main');
        if (null !== $warning && str_contains($warning, 'this one')) {
            $this->answer(PublishHandler::class, ['menu' => $menu], []);
        }

        $result = $this->answer(MenuEditHandler::class, ['menu' => $menu]);

        static::assertSame($warning, $result->screen?->form?->field('handle')?->attributes['warning'] ?? null);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpHost();
    }
}
