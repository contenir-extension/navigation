<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Admin;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Navigation\Admin\ItemForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_slice;

#[Group('unit')]
final class ItemFormTest extends TestCase
{
    private const array PARENTS = ['' => 'The top of the menu'];

    /**
     * Each field's name, type, whether it is required and its value.
     *
     * @param list<Field> $fields
     *
     * @return list<array{string, string, bool, mixed}>
     */
    private static function fields(array $fields): array
    {
        return array_map(static fn(Field $field): array => [
            $field->name,
            $field->type,
            $field->required,
            $field->default,
        ], $fields);
    }

    #[Test]
    public function aNewItemAsksWhatItLinksToItsLabelAndWhereItSits(): void
    {
        static::assertSame(
            [
                ['link',     'link',     false, null],
                ['label',    'text',     false, null],
                ['fragment', 'text',     false, null],
                ['html',     'textarea', false, null],
                ['parent',   'select',   false, ''],
            ],
            self::fields(ItemForm::adding(self::PARENTS)->fields),
        );
    }

    #[Test]
    public function aNewItemShowsWhatWasPosted(): void
    {
        $form = ItemForm::adding(self::PARENTS, [
            'link'   => 'address:/shop',
            'label'  => 'Shop',
            'parent' => '3',
        ]);

        static::assertSame(['address:/shop', 'Shop', '3'], [
            $form->field('link')?->default,
            $form->field('label')?->default,
            $form->field('parent')?->default,
        ]);
    }

    #[Test]
    public function anItemThatExistsAlsoAsksHowItIsDrawn(): void
    {
        static::assertSame(
            [
                ['display', 'select',   false, 'label'],
                ['icon',    'text',     false, null],
                ['new_tab', 'checkbox', false, false],
                ['class',   'text',     false, null],
                ['active',  'checkbox', false, true],
            ],
            self::fields(array_slice(ItemForm::editing(self::PARENTS, [], true, '/about')->fields, offset: 5)),
        );
    }

    #[Test]
    public function anItemThatExistsShowsItsDrawing(): void
    {
        $form = ItemForm::editing(self::PARENTS, [
            'display' => 'icon',
            'new_tab' => true,
            'active'  => false,
        ]);

        static::assertSame(
            ['icon', true, false, ItemForm::DISPLAYS],
            [
                $form->field('display')?->default,
                $form->field('new_tab')?->default,
                $form->field('active')?->default,
                $form->field('display')?->options,
            ],
        );
    }

    #[Test]
    public function theLinkOffersCreateNewUnderThePathGivenWhenAsked(): void
    {
        static::assertSame(
            [
                ['create' => true, 'prefix' => '/about'],
                ['create' => false, 'prefix' => ''],
                ['create' => false, 'prefix' => ''],
            ],
            [
                ItemForm::adding(self::PARENTS, [], true, '/about')->field('link')?->attributes,
                ItemForm::adding(self::PARENTS)->field('link')?->attributes,
                ItemForm::editing(self::PARENTS, [])->field('link')?->attributes,
            ],
        );
    }
}
