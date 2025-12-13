<?php

namespace LdapRecord\Query\Filter;

class OrGroup implements Filter
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
     * Compile the filter to its LDAP string representation.
     */
    public function __toString(): string
    {
        if (empty($this->filters)) {
            return '';
        }

        return '(|'.implode('', array_map(fn (Filter $filter) => (string) $filter, $this->filters)).')';
    }
}
