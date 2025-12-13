<?php

namespace LdapRecord\Query\Filter;

class EndsWith implements Filter
{
    /**
     * Create a new ends with filter.
     */
    public function __construct(
        protected string $attribute,
        protected string $value
    ) {}

    /**
     * Compile the filter to its LDAP string representation.
     */
    public function __toString(): string
    {
        return "({$this->attribute}=*{$this->value})";
    }
}
