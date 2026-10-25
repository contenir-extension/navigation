<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Admin;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\Group as FormGroup;
use Contenir\Extension\Navigation\Admin\Input;
use Contenir\Extension\Navigation\Admin\MenuForm;
use Contenir\Navigation\Menu\Navigation;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class MenuFormTest extends TestCase
{
    private static function input(): Input
    {
        return Input::of((new Psr17Factory())->createServerRequest('POST', '/')
            ->withParsedBody([
                'title'       => ' Header ',
                'handle'      => 'header',
                'description' => 'Top',
            ]));
    }

    #[Test]
    public function aBlankFormShowsNothing(): void
    {
        static::assertSame(
            [null, null, null],
            array_map(static fn(Field $field): mixed => $field->default, MenuForm::of()->fields),
        );
    }

    #[Test]
    public function aChangedMenuKeepsItsIdPlaceAndLiveRevision(): void
    {
        static::assertEquals(
            new Navigation('header', 'Header', 'Top', 3, 7, 9),
            MenuForm::posted(self::input(), new Navigation('old', 'Old', null, 3, 7, 9)),
        );
    }

    #[Test]
    public function aNewMenuComesFirstUntilItIsPlaced(): void
    {
        static::assertEquals(new Navigation('header', 'Header', 'Top'), MenuForm::posted(self::input()));
    }

    #[Test]
    public function asksForATitleAndHandleAndWhereItAppears(): void
    {
        $form = MenuForm::of(['title' => 'Header', 'handle' => 'header', 'description' => 'Top']);

        static::assertSame(
            [
                ['title',       true,  'Header'],
                ['handle',      true,  'header'],
                ['description', false, 'Top'],
            ],
            array_map(static fn(Field $field): array => [
                $field->name,
                $field->required,
                $field->default,
            ], $form->fields),
        );
    }

    #[Test]
    public function fillsTheHandleFromTheTitleWithinNavigationsRule(): void
    {
        $handle = MenuForm::of()->field('handle');

        static::assertSame(
            ['identifier', ['data-identifier' => 'title', 'pattern' => '[a-z][a-z0-9\\-]{0,63}', 'maxlength' => 64]],
            [$handle?->type, $handle?->attributes],
        );
    }

    #[Test]
    public function keepsWhereItAppearsInTheSidebarsInfoCard(): void
    {
        static::assertSame(
            [['menu', 'Menu', false, ['title', 'handle']], ['info', 'Info', true, ['description']]],
            array_map(
                static fn(FormGroup $group): array => [$group->id, $group->legend, $group->aside, $group->fieldNames()],
                MenuForm::of()->groups,
            ),
        );
    }

    #[Test]
    public function showsWhatWasPostedAgain(): void
    {
        static::assertSame(
            ['title' => 'Header', 'handle' => 'header', 'description' => 'Top'],
            MenuForm::values(self::input()),
        );
    }

    #[Test]
    public function warnsBesideTheHandleWhenItIsShared(): void
    {
        static::assertSame(
            'Also the handle of menu "Header".',
            MenuForm::of(shared: 'Also the handle of menu "Header".')->field('handle')?->attributes['warning'] ?? null,
        );
    }
}
