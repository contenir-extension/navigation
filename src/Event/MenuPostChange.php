<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Event;

use Contenir\Extension\Api\Event\AbstractPostEvent;
use Contenir\Extension\Api\Event\SiteChangeEventInterface;

/**
 * After a change to a menu reaches the site: the menu published, a revision restored into a live
 * menu, a change saved to a live menu, which publishes itself, or a live menu's details edited or
 * the menu deleted. The CMS clears the site's caches and sends its webhook after it.
 *
 * @api
 */
final readonly class MenuPostChange extends AbstractPostEvent implements SiteChangeEventInterface
{
    /**
     * @param string $handle The menu's identifier, as the site names it
     */
    public function __construct(
        public string $handle,
    ) {
        parent::__construct();
    }
}
