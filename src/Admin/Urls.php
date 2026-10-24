<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Navigation\NavigationExtension;

use function http_build_query;

/**
 * Where the add-on's screens are, for redirects from one to another.
 */
final class Urls
{
    /**
     * The Navigation screen showing $menu, or the first menu when null.
     */
    public static function menu(?int $menu): string
    {
        return (
            null === $menu ? self::handler(MenusHandler::NAME) : self::handler(MenusHandler::NAME, ['menu' => $menu])
        );
    }

    /**
     * @param array<string, scalar> $query
     */
    private static function handler(string $name, array $query = []): string
    {
        $url = '/admin/extension/' . NavigationExtension::HANDLE . "/{$name}";

        return [] === $query ? $url : "{$url}?" . http_build_query($query);
    }
}
