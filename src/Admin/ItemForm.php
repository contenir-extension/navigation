<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Navigation\Menu\Display;

/**
 * A menu item as a described form: what it links to, chosen in the host's
 * link picker, its label and where it sits, and, once it exists, how it is
 * drawn. An item that links to nothing is text only.
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
     * @param ?string                    $prefix  The path a new page's suggested path starts with
     *
     * @throws InvalidDeclarationException Never: the fields are fixed.
     */
    public static function adding(
        array $parents,
        array $values = [],
        bool $create = false,
        ?string $prefix = null,
    ): Form {
        return new Form(self::linkFields($parents, $values, $create, $prefix));
    }

    /**
     * The form for an item that exists, with how it is drawn.
     *
     * @param array<string, string>      $parents Choices of parent, by id, "" for the top of the menu
     * @param array<string, scalar|null> $values
     *
     * @throws InvalidDeclarationException Never: the fields are fixed.
     */
    public static function editing(array $parents, array $values, bool $create = false, ?string $prefix = null): Form
    {
        $value = static fn(string $name): string|int|float|bool|null => $values[$name] ?? null;

        return new Form([
            ...self::linkFields($parents, $values, $create, $prefix),
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
                'Show on the site',
                FieldType::Checkbox,
                'Turn off to hide the item without deleting it.',
                default: $value('active') ?? true,
            ),
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
    private static function linkFields(array $parents, array $values, bool $create, ?string $prefix): array
    {
        $value = static fn(string $name): string|int|float|bool|null => $values[$name] ?? null;

        return [
            new Field(
                'link',
                'Links to',
                FieldType::Link,
                'A page or other record, with the spot on its page if you need one, or an address. Leave it empty for text only.',
                default: $value('link'),
                attributes: ['create' => $create, 'prefix' => (string) $prefix],
            ),
            new Field(
                'label',
                'Label',
                description: 'Needed for an address or text; a page uses its own title unless you give one.',
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
