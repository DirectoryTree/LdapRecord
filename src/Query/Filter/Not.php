<?php

namespace LdapRecord\Query\Filter;

class Not implements GroupFilter
{
    /**
     * Create a new NOT filter.
     */
    public function __construct(
        protected Filter $filter
    ) {}

    /**
     * Get the wrapped filter.
     */
    public function getFilter(): Filter
    {
        return $this->filter;
    }

    /**
     * Get the filter's operator.
     */
    public function getOperator(): string
    {
        return '!';
    }

    /**
     * Get the raw filter string (without outer parentheses).
     */
    public function getRaw(): string
    {
        return '!'.$this->filter;
    }

    /**
     * Compile the filter to its LDAP string representation.
     */
    public function __toString(): string
    {
        return '('.$this->getRaw().')';
    }
}
