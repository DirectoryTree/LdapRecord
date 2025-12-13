<?php

namespace LdapRecord\Query\Filter;

class OrGroup implements GroupFilter
{
    /**
     * The filters in the group.
     *
     * @var Filter[]
     */
    protected array $filters;

    /**
     * Create a new OR group filter.
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
        return '|';
    }

    /**
     * {@inheritdoc}
     */
    public function getRaw(): string
    {
        return '|'.implode($this->filters);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return '('.$this->getRaw().')';
    }
}
