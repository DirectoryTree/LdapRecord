<?php

namespace LdapRecord\Query\Filter;

class Has implements Filter
{
    /**
     * Create a new has (presence) filter.
     */
    public function __construct(
        protected string $attribute
    ) {}

    /**
     * Compile the filter to its LDAP string representation.
     */
    public function __toString(): string
    {
        return "({$this->attribute}=*)";
    }
}
