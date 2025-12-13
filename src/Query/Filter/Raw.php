<?php

namespace LdapRecord\Query\Filter;

class Raw implements Filter
{
    /**
     * Create a new raw filter.
     */
    public function __construct(
        protected string $value
    ) {}

    /**
     * Compile the filter to its LDAP string representation.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
