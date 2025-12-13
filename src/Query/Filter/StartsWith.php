<?php

namespace LdapRecord\Query\Filter;

class StartsWith implements Filter
{
    /**
     * Create a new starts with filter.
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
        return "({$this->attribute}={$this->value}*)";
    }
}
