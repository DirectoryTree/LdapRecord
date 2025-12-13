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
     * Get the group's operator.
     */
    public function getOperator(): string
    {
        return '&';
    }

    /**
     * Get the raw filter string (without outer parentheses).
     */
    public function getRaw(): string
    {
        return '&'.implode('', array_map(fn (Filter $filter) => (string) $filter, $this->filters));
    }

    /**
     * Compile the filter to its LDAP string representation.
     */
    public function __toString(): string
    {
        if (empty($this->filters)) {
            return '';
        }

        return '('.$this->getRaw().')';
    }
}
