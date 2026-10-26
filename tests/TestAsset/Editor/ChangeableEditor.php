<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\TestAsset\Editor;

use Contenir\Extension\Api\Editor\CurrentEditorInterface;
use Contenir\Extension\Api\Editor\Editor;
use Override;

/**
 * The editor signed in, as a test sets it: one who can be recorded for one request, one who
 * can't for the next.
 */
final class ChangeableEditor implements CurrentEditorInterface
{
    public function __construct(
        public ?Editor $editor = null,
    ) {}

    #[Override]
    public function current(): ?Editor
    {
        return $this->editor;
    }
}
