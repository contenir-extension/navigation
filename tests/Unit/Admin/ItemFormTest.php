<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Admin;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Form\Group as FormGroup;
use Contenir\Extension\Navigation\Admin\ItemForm;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_slice;

#[Group('unit')]
final class ItemFormTest extends TestCase
{
    private const array PARENTS = ['' => 'None (top level)'];

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

    /**
     * @return list<array{string, string, list<list<string>>, bool}>
     */
    private static function groupsOf(Form $form): array
    {
        return array_map(
            static fn(FormGroup $group): array => [
                $group->id,
                $group->legend,
                $group->rows,
                $group->folded,
            ],
            $form->groups,
        );
    }

    #[Test]
    public function aNewItemAsksWhatItLinksToItsLabelAndWhereItSits(): void
    {
        static::assertSame(
            [
                ['link',   'link',     false, null],
                ['label',  'text',     false, null],
                ['html',   'textarea', false, null],
                ['parent', 'select',   false, ''],
            ],
            self::fields(ItemForm::adding(self::PARENTS)->fields),
        );
    }

    #[Test]
    public function aNewItemFoldsItsTextAndPlaceAwayUnderAdvanced(): void
    {
        static::assertSame(
            [['item', '', [['link']], false], ['advanced', 'Advanced', [['html'], ['parent']], true]],
            self::groupsOf(ItemForm::adding(self::PARENTS)),
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
    public function anItemsVisibilityIsASwitchCalledVisible(): void
    {
        $field = ItemForm::editing(self::PARENTS, [])->field('active');

        static::assertSame(['Visible', ['switch' => true]], [$field?->label, $field?->attributes]);
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
            self::fields(array_slice(ItemForm::editing(self::PARENTS, [], true, '/about')->fields, offset: 4)),
        );
    }

    #[Test]
    public function anItemThatExistsFoldsAllButItsLinkAndVisibilityAwayUnderAdvanced(): void
    {
        static::assertSame(
            [
                ['item', '', [['link'], ['active']], false],
                ['advanced', 'Advanced', [['html'], ['parent'], ['display'], ['icon'], ['new_tab'], ['class']], true],
            ],
            self::groupsOf(ItemForm::editing(self::PARENTS, [])),
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
    public function theLinkIsTheItemSayingHowItsLabelFallsBack(): void
    {
        $field = ItemForm::adding(self::PARENTS)->field('link');

        static::assertSame(
            [
                'Item',
                'Choose a page or other record, with an anchor on its page if you need one, or an address, and give it a '
                    . 'label, or leave the label empty to use the page\'s own title. With no link it is text only.',
            ],
            [$field?->label, $field?->description],
        );
    }

    #[Test]
    public function theLinkOffersCreateNewUnderThePathGivenWhenAsked(): void
    {
        static::assertSame(
            [
                ['create' => true, 'label' => 'label', 'external' => 'new_tab'],
                ['create' => false, 'label' => 'label', 'external' => 'new_tab'],
                ['create' => false, 'label' => 'label', 'external' => 'new_tab'],
            ],
            [
                ItemForm::adding(self::PARENTS, [], true, '/about')->field('link')?->attributes,
                ItemForm::adding(self::PARENTS)->field('link')?->attributes,
                ItemForm::editing(self::PARENTS, [])->field('link')?->attributes,
            ],
        );
    }
}
