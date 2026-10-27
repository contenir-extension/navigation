<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Form\Group;
use Contenir\Navigation\Menu\Display;

/**
 * A menu item as a described form: what it links to, chosen in the host's
 * link picker, with its label drawn in the picker's bar, and, once it exists,
 * whether it is visible; its text, where it sits and how it is drawn are
 * folded away under Advanced. An item that links to nothing is text only.
 */
final class ItemForm
{
    /**
     * @var array<string, string>
     */
    public const array DISPLAYS = [
        Display::Label->value     => 'Its label',
        Display::Icon->value      => 'Its icon',
        Display::IconLabel->value => 'Its icon and label',
    ];

    /**
     * The form for a new item.
     *
     * @param array<string, string>      $parents Choices of parent, by id, "" for the top of the menu
     * @param array<string, scalar|null> $values
     *
     * @throws InvalidDeclarationException Never: the fields are fixed.
     */
    public static function adding(array $parents, array $values = [], bool $create = false): Form
    {
        return new Form(self::linkFields($parents, $values, $create), [
            new Group('item', '', ['link']),
            new Group('advanced', 'Advanced', ['html', 'parent'], folded: true),
        ]);
    }

    /**
     * The form for an item that exists, with how it is drawn.
     *
     * @param array<string, string>      $parents Choices of parent, by id, "" for the top of the menu
     * @param array<string, scalar|null> $values
     *
     * @throws InvalidDeclarationException Never: the fields are fixed.
     */
    public static function editing(array $parents, array $values, bool $create = false): Form
    {
        $value = static fn(string $name): string|int|float|bool|null => $values[$name] ?? null;

        return new Form([
            ...self::linkFields($parents, $values, $create),
            new Field(
                'display',
                'Show',
                FieldType::Select,
                default: $value('display') ?? Display::Label->value,
                options: self::DISPLAYS,
            ),
            new Field(
                'icon',
                'Icon',
                description: 'A name the site knows its icon by, such as phone or instagram.',
                default: $value('icon'),
            ),
            new Field('new_tab', 'Open in a new tab', FieldType::Checkbox, default: $value('new_tab') ?? false),
            new Field(
                'class',
                'Extra classes',
                description: 'For the site\'s styles, separated by spaces.',
                default: $value('class'),
            ),
            new Field(
                'active',
                'Visible',
                FieldType::Checkbox,
                'Turn off to hide the item without deleting it.',
                default: $value('active') ?? true,
                attributes: ['switch' => true],
            ),
        ], [
            new Group('item', '', ['link', 'active']),
            new Group('advanced', 'Advanced', ['html', 'parent', 'display', 'icon', 'new_tab', 'class'], folded: true),
        ]);
    }

    /**
     * @param array<string, string>      $parents
     * @param array<string, scalar|null> $values
     *
     * @return list<Field>
     *
     * @throws InvalidDeclarationException Never: the fields are fixed.
     */
    private static function linkFields(array $parents, array $values, bool $create): array
    {
        $value = static fn(string $name): string|int|float|bool|null => $values[$name] ?? null;

        return [
            new Field(
                'link',
                'Item',
                FieldType::Link,
                'Choose a page or other record, with an anchor on its page if you need one, or an address, and give it a '
                    . 'label, or leave the label empty to use the page\'s own title. With no link it is text only.',
                default: $value('link'),
                attributes: [
                    'create'   => $create,
                    'label'    => 'label',
                    'external' => 'new_tab',
                ],
            ),
            new Field(
                'label',
                'Label',
                description: 'Leave it empty to use the page\'s own title; an address or text needs one.',
                default: $value('label'),
            ),
            new Field(
                'html',
                'Text',
                FieldType::Textarea,
                'For text only: bold, italics and links are kept.',
                default: $value('html'),
            ),
            new Field('parent', 'Parent', FieldType::Select, default: $value('parent') ?? '', options: $parents),
        ];
    }
}
