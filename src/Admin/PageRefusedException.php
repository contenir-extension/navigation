<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Contenir\Extension\Api\Event\ActionResult;
use RuntimeException;

use function array_filter;
use function array_merge;
use function array_values;
use function implode;

/**
 * A page the item form asked for that the site didn't make, with what it
 * said, for the editor.
 */
final class PageRefusedException extends RuntimeException
{
    public static function because(string $message): self
    {
        return new self($message);
    }

    /**
     * The site's reasons for not making the page: its message and each
     * field's errors.
     */
    public static function from(ActionResult $result): self
    {
        $reasons = array_merge([$result->message], ...array_values($result->errors));

        return new self(implode(' ', array_filter($reasons, static fn(string $reason): bool => '' !== $reason)));
    }
}
