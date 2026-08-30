<?php

namespace Artlosk\LaravelGridView\Rows;

use InvalidArgumentException;

class ExpandedGroup
{
    /**
     * @param  list<ExpandedRow>  $rows
     * @param  list<string>  $sharedColumns
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $sharedColumns = [],
        public readonly ?string $class = null,
    ) {
        if ($rows === []) {
            throw new InvalidArgumentException('An expanded group must contain at least one row.');
        }

        if (!array_is_list($rows)) {
            throw new InvalidArgumentException('Expanded group rows must be a list of ExpandedRow instances.');
        }

        foreach ($rows as $row) {
            if (!$row instanceof ExpandedRow) {
                throw new InvalidArgumentException('Expanded group rows must be a list of ExpandedRow instances.');
            }
        }

        if (!array_is_list($sharedColumns)) {
            throw new InvalidArgumentException(
                'Shared columns must be a list of unique, non-empty strings.',
            );
        }

        foreach ($sharedColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new InvalidArgumentException(
                    'Shared columns must be a list of unique, non-empty strings.',
                );
            }
        }

        if (count($sharedColumns) !== count(array_unique($sharedColumns))) {
            throw new InvalidArgumentException(
                'Shared columns must be a list of unique, non-empty strings.',
            );
        }
    }

    /**
     * @param  list<ExpandedRow>  $rows
     * @param  list<string>  $sharedColumns
     */
    public static function make(array $rows, array $sharedColumns = [], ?string $class = null): self
    {
        return new self($rows, $sharedColumns, $class);
    }

    public static function single(?string $class = null): self
    {
        return new self([new ExpandedRow()], class: $class);
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    public function shares(string $columnKey): bool
    {
        return in_array($columnKey, $this->sharedColumns, true);
    }
}
