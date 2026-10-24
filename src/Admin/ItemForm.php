<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Navigation\Menu\Display;

/**
 * A menu item as a described form: what it links to, its label and where it
 * sits, and, once it exists, how it is drawn.
 */
final class ItemForm
{
    public const string LINKS_TO_RECORD = 'record';

    public const string LINKS_TO_URL = 'url';

    public const string LINKS_TO_NOTHING = 'text';

    /**
     * @var array<string, string>
     */
    public const array LINKS_TO = [
        self::LINKS_TO_RECORD  => 'A page or other record',
        self::LINKS_TO_URL     => 'An address',
        self::LINKS_TO_NOTHING => 'Nothing: text only',
    ];

    /**
     * What an item can link to when the host lends no records.
     *
     * @var array<string, string>
     */
    public const array WITHOUT_RECORDS = [
        self::LINKS_TO_URL     => 'An address',
        self::LINKS_TO_NOTHING => 'Nothing: text only',
    ];

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
     * @param array<string, string>     $records Choices of record, by "<section>:<id>"
     * @param array<string, string>     $parents Choices of parent, by id, "" for the top of the menu
     * @param array<string, scalar|null> $values
     *
     * @throws InvalidDeclarationException Never: the fields are fixed.
     */
    public static function adding(array $records, array $parents, array $values = []): Form
    {
        return new Form(self::linkFields($records, $parents, $values));
    }

    /**
     * The form for an item that exists, with how it is drawn.
     *
     * @param array<string, string>     $records Choices of record, by "<section>:<id>"
     * @param array<string, string>     $parents Choices of parent, by id, "" for the top of the menu
     * @param array<string, scalar|null> $values
     *
     * @throws InvalidDeclarationException Never: the fields are fixed.
     */
    public static function editing(array $records, array $parents, array $values): Form
    {
        $value = static fn(string $name): string|int|float|bool|null => $values[$name] ?? null;

        return new Form([
            ...self::linkFields($records, $parents, $values),
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
     * @param array<string, string>      $records
     * @param array<string, string>      $parents
     * @param array<string, scalar|null> $values
     *
     * @return list<Field>
     *
     * @throws InvalidDeclarationException Never: the fields are fixed.
     */
    private static function linkFields(array $records, array $parents, array $values): array
    {
        $value = static fn(string $name): string|int|float|bool|null => $values[$name] ?? null;

        return [
            new Field(
                'links_to',
                'Links to',
                FieldType::Radio,
                required: true,
                default: $value('links_to') ?? ([] === $records ? self::LINKS_TO_URL : self::LINKS_TO_RECORD),
                options: [] === $records ? self::WITHOUT_RECORDS : self::LINKS_TO,
            ),
            ...(
                [] === $records
                    ? []
                    : [new Field(
                        'record',
                        'Page or record',
                        FieldType::Select,
                        default: $value('record'),
                        options: $records,
                    )]
            ),
            new Field(
                'url',
                'Address',
                description: 'A page on this site such as /contact, a full link, a spot on the page such as #booking, an email address or a phone number.',
                default: $value('url'),
            ),
            new Field(
                'label',
                'Label',
                description: 'Needed for an address or text; a page uses its own title unless you give one.',
                default: $value('label'),
            ),
            new Field(
                'fragment',
                'Spot on the page',
                description: 'For a page or record: the id of a section on it, such as staff.',
                default: $value('fragment'),
            ),
            new Field(
                'html',
                'Text',
                FieldType::Textarea,
                'For text only: bold, italics and links are kept.',
                default: $value('html'),
            ),
            new Field('parent', 'Under', FieldType::Select, default: $value('parent') ?? '', options: $parents),
        ];
    }
}
