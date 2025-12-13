<?php

namespace LdapRecord\Query\Filter;

class AndGroup implements GroupFilter
{
    /**
     * The filters in the group.
     *
     * @var Filter[]
     */
    protected array $filters;

    /**
     * Create a new AND group filter.
     */
    public function __construct(Filter ...$filters)
    {
        $this->filters = $filters;
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
    public function getOperator(): string
    {
        return '&';
    }

    /**
     * {@inheritdoc}
     */
    public function getRaw(): string
    {
        return '&'.implode($this->filters);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return '('.$this->getRaw().')';
    }
}
