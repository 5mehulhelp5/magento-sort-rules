<?php

declare(strict_types=1);

namespace StackNuts\SortRules\Model;

/**
 * Immutable representation of a single admin-configured sort rule: one row
 * from the "Sort Rules" grid, translated into something the rest of the
 * module can work with instead of passing raw config arrays around.
 */
class Rule
{
    public function __construct(
        private readonly string $code,
        private readonly string $attribute,
        private readonly string $label,
        private readonly string $direction,
        private readonly bool $forceDirection,
        private readonly int $sortOrder
    ) {
    }

    public static function fromConfigRow(array $row): self
    {
        $direction = ($row['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return new self(
            (string) ($row['code'] ?? ''),
            (string) ($row['target_attribute'] ?? ''),
            (string) ($row['label'] ?? ''),
            $direction,
            !empty($row['force_direction']),
            (int) ($row['sort_order'] ?? 0)
        );
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function isForceDirection(): bool
    {
        return $this->forceDirection;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
