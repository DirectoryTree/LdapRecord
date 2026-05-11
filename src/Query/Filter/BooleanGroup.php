<?php

namespace LdapRecord\Query\Filter;

abstract class BooleanGroup implements GroupFilter
{
    /**
     * Determine if this group should be preserved when nesting filters.
     */
    protected bool $nested = false;

    /**
     * The filters in the group.
     *
     * @var Filter[]
     */
    protected array $filters;

    /**
     * Create a new boolean group filter.
     */
    public function __construct(Filter ...$filters)
    {
        $this->filters = $filters;
    }

    /**
     * Create a new nested boolean group filter.
     */
    public static function nested(Filter ...$filters): static
    {
        $group = new static(...$filters);

        $group->nested = true;

        return $group;
    }

    /**
     * Determine if this group should be preserved when nesting filters.
     */
    public function isNested(): bool
    {
        return $this->nested;
    }

    /**
     * Get the filters in the group.
     *
     * @return Filter[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * {@inheritdoc}
     */
    public function getRaw(): string
    {
        return $this->getOperator().implode($this->filters);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return '('.$this->getRaw().')';
    }
}
