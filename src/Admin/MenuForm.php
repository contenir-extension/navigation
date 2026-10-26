<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Form\Group;
use Contenir\Navigation\Exception\InvalidItemException;
use Contenir\Navigation\Exception\StorageException;
use Contenir\Navigation\Menu\Navigation;
use Contenir\Navigation\Storage\NavigationRepositoryInterface;

use function array_filter;
use function array_map;
use function count;
use function implode;
use function sprintf;

/**
 * A menu's settings as a described form: its title, and in the sidebar's Info card the identifier
 * templates ask for it by, filled from the title until the editor changes it, and where it appears
 * on the site. An identifier other menus have too is allowed; the form says which menu the site shows.
 */
final class MenuForm
{
    /** The handles Navigation accepts, as an HTML pattern. */
    private const string HANDLE_PATTERN = '[a-z][a-z0-9\-]{0,63}';

    /**
     * The form, each field showing $values, and the handle warning $shared when there is one.
     *
     * @param array{title?: string, handle?: string, description?: string|null} $values
     *
     * @throws InvalidDeclarationException Never: the fields are fixed.
     */
    public static function of(array $values = [], ?string $shared = null): Form
    {
        $handle = [
            'data-identifier' => 'title',
            'pattern'         => self::HANDLE_PATTERN,
            'maxlength'       => 64,
        ];
        if (null !== $shared) {
            $handle['warning'] = $shared;
        }

        return new Form(
            [
                new Field('title', 'Title', required: true, default: $values['title'] ?? null),
                new Field(
                    'handle',
                    'Identifier',
                    FieldType::Identifier,
                    'What the site\'s templates ask for this menu by, such as header or footer: lowercase letters, digits and dashes.',
                    required: true,
                    default: $values['handle'] ?? null,
                    attributes: $handle,
                ),
                new Field(
                    'description',
                    'Where it appears',
                    FieldType::Textarea,
                    'A note for editors, such as "Across the top of every page".',
                    default: $values['description'] ?? null,
                ),
            ],
            [
                new Group('menu', 'Menu', ['title']),
                new Group('info', 'Info', ['handle', 'description'], aside: true),
            ],
        );
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
     * What to tell the editor when menus other than $menu have $handle too: their titles, and
     * which menu the site shows, the first published one in menu order. Null when no other menu
     * has it.
     *
     * @throws StorageException When the menus can't be read.
     */
    public static function shared(NavigationRepositoryInterface $menus, string $handle, ?int $menu = null): ?string
    {
        $others = array_filter(
            $menus->navigationsByHandle($handle),
            static fn(Navigation $other): bool => $other->id !== $menu,
        );
        if ([] === $others) {
            return null;
        }

        $shown = $menus->navigationByHandle($handle);
        $site  = match (true) {
            null === $shown?->revisionId => 'None of them is published yet.',
            $shown->id === $menu => 'The site shows this one, the first published in menu order.',
            default => sprintf('The site shows "%s", the first published in menu order.', $shown->title),
        };

        return sprintf(
            'Also the identifier of %s %s. %s',
            1 === count($others) ? 'menu' : 'menus',
            implode(', ', array_map(static fn(Navigation $other): string => sprintf('"%s"', $other->title), $others)),
            $site,
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
