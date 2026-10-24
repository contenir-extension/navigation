<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Navigation\Exception\InvalidItemException;
use Contenir\Navigation\Menu\Navigation;

/**
 * A menu's settings as a described form: its title, the handle templates ask
 * for it by, and where it appears on the site.
 */
final class MenuForm
{
    /**
     * The form, each field showing $values.
     *
     * @param array{title?: string, handle?: string, description?: string|null} $values
     *
     * @throws InvalidDeclarationException Never: the fields are fixed.
     */
    public static function of(array $values = []): Form
    {
        return new Form([
            new Field('title', 'Title', required: true, default: $values['title'] ?? null),
            new Field(
                'handle',
                'Handle',
                description: 'What the site\'s templates ask for this menu by, such as header or footer: lowercase letters, digits and dashes.',
                required: true,
                default: $values['handle'] ?? null,
            ),
            new Field(
                'description',
                'Where it appears',
                FieldType::Textarea,
                'A note for editors, such as "Across the top of every page".',
                default: $values['description'] ?? null,
            ),
        ]);
    }

    /**
     * The menu what was posted describes, keeping $menu's id, order and live
     * revision when it changes one.
     *
     * @throws InvalidItemException When the handle or title breaks its rule.
     */
    public static function posted(Input $input, ?Navigation $menu = null): Navigation
    {
        return new Navigation(
            $input->posted('handle'),
            $input->posted('title'),
            $input->postedOrNull('description'),
            $menu->sequence ?? 0,
            $menu?->id,
            $menu?->revisionId,
        );
    }

    /**
     * What was posted, to show again when the form is refused.
     *
     * @return array{title: string, handle: string, description: string|null}
     */
    public static function values(Input $input): array
    {
        return [
            'title'       => $input->posted('title'),
            'handle'      => $input->posted('handle'),
            'description' => $input->postedOrNull('description'),
        ];
    }
}
