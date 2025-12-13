<?php

namespace LdapRecord\Query\Filter;

class Not implements Filter
{
    /**
     * Create a new NOT filter.
     */
    public function __construct(
        protected Filter $filter
    ) {}

    /**
     * Compile the filter to its LDAP string representation.
     */
    public function __toString(): string
    {
        return '(!'.$this->filter.')';
    }
}
