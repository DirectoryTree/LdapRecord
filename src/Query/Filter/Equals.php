<?php

namespace LdapRecord\Query\Filter;

class Equals implements Filter
{
    /**
     * Create a new equals filter.
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
        return "({$this->attribute}={$this->value})";
    }
}
