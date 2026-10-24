<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Admin;

use Contenir\Extension\Api\Form\Field;
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
    public function showsWhatWasPostedAgain(): void
    {
        static::assertSame(
            ['title' => 'Header', 'handle' => 'header', 'description' => 'Top'],
            MenuForm::values(self::input()),
        );
    }
}
