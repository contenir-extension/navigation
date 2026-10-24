<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Admin;

use Psr\Http\Message\ServerRequestInterface;

use function ctype_digit;
use function is_array;
use function is_scalar;
use function trim;

/**
 * What a request carries: its query and what was posted, read as trimmed
 * text or ids.
 */
final readonly class Input
{
    /**
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $posted
     */
    private function __construct(
        private array $query,
        private array $posted,
    ) {}

    public static function of(ServerRequestInterface $request): self
    {
        $posted = $request->getParsedBody();

        return new self($request->getQueryParams(), is_array($posted) ? $posted : []);
    }

    /**
     * Whether a posted checkbox was ticked.
     */
    public function checked(string $name): bool
    {
        return '' !== $this->posted($name) && '0' !== $this->posted($name);
    }

    /**
     * A positive whole number from the query, or else from what was posted,
     * or null when there is none.
     */
    public function id(string $name): ?int
    {
        $value = $this->text($this->query, $name);
        if ('' === $value) {
            $value = $this->posted($name);
        }

        return ctype_digit($value) && '0' !== $value ? (int) $value : null;
    }

    /**
     * A posted value as trimmed text, empty when it wasn't posted.
     */
    public function posted(string $name): string
    {
        return $this->text($this->posted, $name);
    }

    /**
     * A posted value as trimmed text, or null when it is empty.
     */
    public function postedOrNull(string $name): ?string
    {
        $value = $this->posted($name);

        return '' === $value ? null : $value;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function text(array $values, string $name): string
    {
        return is_scalar($values[$name] ?? null) ? trim((string) $values[$name]) : '';
    }
}
