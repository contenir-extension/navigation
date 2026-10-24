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
    private const array RECORDS = ['page:1' => 'Page: About us'];

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
                ['links_to', 'radio',    true,  'record'],
                ['record',   'select',   false, null],
                ['url',      'text',     false, null],
                ['label',    'text',     false, null],
                ['fragment', 'text',     false, null],
                ['html',     'textarea', false, null],
                ['parent',   'select',   false, ''],
            ],
            self::fields(ItemForm::adding(self::RECORDS, self::PARENTS)->fields),
        );
    }

    #[Test]
    public function aNewItemShowsWhatWasPosted(): void
    {
        $form = ItemForm::adding(self::RECORDS, self::PARENTS, [
            'links_to' => 'text',
            'label'    => 'Shop',
            'parent'   => '3',
        ]);

        static::assertSame(['text', 'Shop', '3'], [
            $form->field('links_to')?->default,
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
            self::fields(array_slice(ItemForm::editing(self::RECORDS, self::PARENTS, [])->fields, offset: 7)),
        );
    }

    #[Test]
    public function anItemThatExistsShowsItsDrawing(): void
    {
        $form = ItemForm::editing(self::RECORDS, self::PARENTS, [
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
    public function withoutRecordsAnItemLinksToAnAddressOrNothing(): void
    {
        $form = ItemForm::adding([], self::PARENTS);

        static::assertSame(
            [ItemForm::WITHOUT_RECORDS, 'url', null],
            [$form->field('links_to')?->options, $form->field('links_to')?->default, $form->field('record')],
        );
    }

    #[Test]
    public function withRecordsAnItemCanLinkToAnyOfThem(): void
    {
        $form = ItemForm::adding(self::RECORDS, self::PARENTS);

        static::assertSame([ItemForm::LINKS_TO, self::RECORDS], [
            $form->field('links_to')?->options,
            $form->field('record')?->options,
        ]);
    }
}
